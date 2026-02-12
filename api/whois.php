<?php
/**
 * WHOIS 查询 API
 * 优先使用免费 RDAP 公共数据，失败时回退到服务器 whois 命令。
 */

header('Content-Type: application/json; charset=UTF-8');

function sendJson($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeDomain($domain)
{
    $domain = trim(strtolower((string)$domain));
    if ($domain === '') {
        return '';
    }
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    $domain = preg_replace('/:\d+$/', '', $domain);
    return $domain;
}

function isValidDomain($domain)
{
    return (bool)preg_match('/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/i', $domain);
}

function httpGetJson($url, $timeout = 10)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'],
            CURLOPT_USERAGENT => 'NameDeal-WHOIS/1.0',
        ]);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        return [
            'ok' => $body !== false && $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode,
            'data' => is_string($body) ? json_decode($body, true) : null,
            'raw' => $body,
            'error' => $error,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "Accept: application/rdap+json, application/json\r\nUser-Agent: NameDeal-WHOIS/1.0\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    $headers = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    if (!empty($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $m)) {
        $status = (int)$m[1];
    }

    return [
        'ok' => $body !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'data' => is_string($body) ? json_decode($body, true) : null,
        'raw' => $body,
        'error' => $body === false ? 'request_failed' : '',
    ];
}

function loadRdapBootstrap()
{
    $cacheFile = __DIR__ . '/../data/rdap_dns_bootstrap_cache.json';
    $maxAge = 24 * 3600;

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $maxAge)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['services'])) {
            return $cached;
        }
    }

    $response = httpGetJson('https://data.iana.org/rdap/dns.json', 10);
    if ($response['ok'] && is_array($response['data']) && !empty($response['data']['services'])) {
        @file_put_contents($cacheFile, json_encode($response['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response['data'];
    }

    // 缓存不可用且拉取失败时，最后尝试读取旧缓存
    if (file_exists($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['services'])) {
            return $cached;
        }
    }
    return null;
}

function findRdapBaseUrl($bootstrap, $tld)
{
    if (!is_array($bootstrap) || empty($bootstrap['services']) || $tld === '') {
        return '';
    }

    foreach ($bootstrap['services'] as $service) {
        if (!is_array($service) || count($service) < 2 || !is_array($service[0]) || !is_array($service[1])) {
            continue;
        }
        $tlds = array_map('strtolower', $service[0]);
        if (in_array(strtolower($tld), $tlds, true)) {
            return (string)($service[1][0] ?? '');
        }
    }
    return '';
}

function extractVcardValue($vcardArray, $keys)
{
    if (!is_array($vcardArray) || !isset($vcardArray[1]) || !is_array($vcardArray[1])) {
        return '';
    }
    foreach ($vcardArray[1] as $entry) {
        if (!is_array($entry) || count($entry) < 4) {
            continue;
        }
        $name = strtolower((string)$entry[0]);
        if (in_array($name, $keys, true)) {
            return trim((string)$entry[3]);
        }
    }
    return '';
}

function findRegistrarFromEntities($entities)
{
    if (!is_array($entities)) {
        return '';
    }

    foreach ($entities as $entity) {
        if (!is_array($entity)) {
            continue;
        }
        $roles = array_map('strtolower', (array)($entity['roles'] ?? []));
        if (in_array('registrar', $roles, true)) {
            $name = extractVcardValue($entity['vcardArray'] ?? null, ['fn', 'org']);
            if ($name !== '') {
                return $name;
            }
            if (!empty($entity['handle'])) {
                return (string)$entity['handle'];
            }
        }
    }

    // 某些注册局会把 registrar 放在子 entities
    foreach ($entities as $entity) {
        $sub = $entity['entities'] ?? null;
        $name = findRegistrarFromEntities($sub);
        if ($name !== '') {
            return $name;
        }
    }
    return '';
}

function extractEventDate($events, $actions)
{
    if (!is_array($events)) {
        return '';
    }
    $actions = array_map('strtolower', $actions);
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $action = strtolower((string)($event['eventAction'] ?? ''));
        if (in_array($action, $actions, true)) {
            return (string)($event['eventDate'] ?? '');
        }
    }
    return '';
}

function normalizeDateString($value)
{
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    return gmdate('Y-m-d', $ts);
}

function isInvalidNameserver($ns)
{
    $value = strtolower(trim((string)$ns));
    $value = rtrim($value, '.');
    if ($value === '') {
        return true;
    }

    $invalidValues = [
        'not.defined',
        'undefined',
        'unknown',
        'none',
        'n/a',
        'null',
        '-',
    ];
    return in_array($value, $invalidValues, true);
}

function queryByRdap($domain)
{
    $tld = strtolower((string)substr(strrchr($domain, '.'), 1));
    if ($tld === '') {
        return ['error' => 'Invalid domain'];
    }

    $bootstrap = loadRdapBootstrap();
    $base = findRdapBaseUrl($bootstrap, $tld);
    if ($base === '') {
        return ['error' => 'RDAP server not found for this TLD'];
    }

    $url = rtrim($base, '/') . '/domain/' . rawurlencode($domain);
    $response = httpGetJson($url, 10);

    if ($response['status'] === 404) {
        return ['domain' => $domain, 'available' => true];
    }
    if (!$response['ok'] || !is_array($response['data'])) {
        return ['error' => 'RDAP query failed'];
    }

    $data = $response['data'];
    if (isset($data['errorCode']) && (int)$data['errorCode'] === 404) {
        return ['domain' => $domain, 'available' => true];
    }

    $registrar = findRegistrarFromEntities($data['entities'] ?? []);
    $created = extractEventDate($data['events'] ?? [], ['registration', 'registered']);
    $expires = extractEventDate($data['events'] ?? [], ['expiration', 'expiry', 'expires']);
    $updated = extractEventDate($data['events'] ?? [], ['last changed', 'last update of rdap database', 'updated']);

    $nameservers = [];
    foreach ((array)($data['nameservers'] ?? []) as $ns) {
        if (!is_array($ns)) {
            continue;
        }
        $name = strtolower(trim((string)($ns['ldhName'] ?? $ns['unicodeName'] ?? '')));
        if ($name !== '' && !isInvalidNameserver($name)) {
            $nameservers[] = $name;
        }
    }
    $nameservers = array_values(array_unique($nameservers));

    $statuses = (array)($data['status'] ?? []);
    $statusText = !empty($statuses) ? implode(', ', $statuses) : '';

    return [
        'domain' => (string)($data['ldhName'] ?? $domain),
        'registrar' => $registrar,
        'created' => normalizeDateString($created),
        'expires' => normalizeDateString($expires),
        'updated' => normalizeDateString($updated),
        'status' => $statusText,
        'nameservers' => $nameservers,
    ];
}

function firstMatch($text, $patterns)
{
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            return trim((string)$m[1]);
        }
    }
    return '';
}

function queryByWhoisCommand($domain)
{
    $which = @shell_exec('command -v whois 2>/dev/null');
    if (!is_string($which) || trim($which) === '') {
        return ['error' => 'WHOIS command is not installed'];
    }

    $output = @shell_exec('whois ' . escapeshellarg($domain) . ' 2>/dev/null');
    if (!is_string($output) || trim($output) === '') {
        return ['error' => 'WHOIS query failed'];
    }

    if (preg_match('/No match for|NOT FOUND|No Data Found|DOMAIN NOT FOUND|Status:\s+free|is available/i', $output)) {
        return ['domain' => $domain, 'available' => true];
    }

    $statuses = [];
    if (preg_match_all('/^Domain Status:\s*([^\r\n]+)/mi', $output, $mStatus)) {
        $statuses = array_values(array_unique(array_map('trim', $mStatus[1])));
    }

    $nameservers = [];
    if (preg_match_all('/^(?:Name Server|nserver):\s*([^\r\n\s]+)/mi', $output, $mNs)) {
        $nameservers = array_values(array_unique(array_map(function ($v) {
            return strtolower(trim((string)$v));
        }, $mNs[1])));
        $nameservers = array_values(array_filter($nameservers, function ($ns) {
            return !isInvalidNameserver($ns);
        }));
    }

    $registrar = firstMatch($output, [
        '/^Registrar:\s*(.+)$/mi',
        '/^Sponsoring Registrar:\s*(.+)$/mi',
    ]);
    $created = firstMatch($output, [
        '/^Creation Date:\s*(.+)$/mi',
        '/^Created On:\s*(.+)$/mi',
        '/^Registered On:\s*(.+)$/mi',
    ]);
    $expires = firstMatch($output, [
        '/^Registry Expiry Date:\s*(.+)$/mi',
        '/^Registrar Registration Expiration Date:\s*(.+)$/mi',
        '/^Expiry Date:\s*(.+)$/mi',
        '/^Expiration Date:\s*(.+)$/mi',
    ]);
    $updated = firstMatch($output, [
        '/^Updated Date:\s*(.+)$/mi',
        '/^Last Updated On:\s*(.+)$/mi',
    ]);

    return [
        'domain' => $domain,
        'registrar' => $registrar,
        'created' => normalizeDateString($created),
        'expires' => normalizeDateString($expires),
        'updated' => normalizeDateString($updated),
        'status' => implode(', ', $statuses),
        'nameservers' => $nameservers,
    ];
}

$domain = normalizeDomain($_GET['domain'] ?? '');
if (!isValidDomain($domain)) {
    sendJson(['error' => 'Invalid domain parameter'], 400);
}

$result = queryByRdap($domain);
if (isset($result['error'])) {
    $fallback = queryByWhoisCommand($domain);
    if (!isset($fallback['error'])) {
        sendJson($fallback);
    }
    sendJson(['error' => 'WHOIS query failed'], 500);
}

sendJson($result);
