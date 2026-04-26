<?php
/**
 * WHOIS 查询 API
 * 使用 https://api.who.ga/{domain} 获取 WHOIS/RDAP 数据，并归一化为前端字段。
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

function fetchWhoGaJson($domain, $timeout = 12)
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'curl extension is not available'];
    }

    $url = 'https://api.who.ga/' . rawurlencode($domain);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'NameDeal-WHOIS/1.1',
    ]);

    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    return [
        'ok' => $body !== false && $httpCode >= 200 && $httpCode < 300,
        'status' => $httpCode,
        'data' => is_string($body) ? json_decode($body, true) : null,
        'error' => $error,
    ];
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
            $value = $entry[3];
            return trim(is_array($value) ? implode(' ', array_filter($value)) : (string)$value);
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

    foreach ($entities as $entity) {
        $name = findRegistrarFromEntities($entity['entities'] ?? null);
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
            return html_entity_decode(trim((string)($event['eventDate'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    return '';
}

function normalizeDateString($value)
{
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/&nbsp;?/i', ' ', $value);
    $value = preg_replace('/\xC2\xA0/u', ' ', $value);
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/\s+/', ' ', $value);
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
    return in_array($value, ['not.defined', 'undefined', 'unknown', 'none', 'n/a', 'null', '-'], true);
}

function normalizeWhoGaResponse($domain, $payload)
{
    if (!is_array($payload)) {
        return ['error' => 'Invalid WHOIS API response'];
    }

    $result = $payload['result'] ?? $payload['registrarResult'] ?? $payload['registryResult'] ?? null;
    if (isset($payload['status']) && (int)$payload['status'] === 404) {
        return ['domain' => $domain, 'available' => true];
    }
    if (is_array($result) && isset($result['errorCode']) && (int)$result['errorCode'] === 404) {
        return ['domain' => $domain, 'available' => true];
    }
    if (!is_array($result)) {
        return ['error' => $payload['error'] ?? 'WHOIS API query failed'];
    }

    $nameservers = [];
    foreach ((array)($result['nameservers'] ?? []) as $ns) {
        if (is_array($ns)) {
            $name = strtolower(trim((string)($ns['ldhName'] ?? $ns['unicodeName'] ?? '')));
        } else {
            $name = strtolower(trim((string)$ns));
        }
        $name = rtrim($name, '.');
        if (!isInvalidNameserver($name)) {
            $nameservers[] = $name;
        }
    }
    $nameservers = array_values(array_unique($nameservers));

    $statuses = array_values(array_filter(array_map('trim', (array)($result['status'] ?? []))));
    $registrar = findRegistrarFromEntities($result['entities'] ?? []);
    if ($registrar === '' && !empty($result['registrar'])) {
        $registrar = (string)$result['registrar'];
    }

    return [
        'domain' => (string)($result['ldhName'] ?? $result['unicodeName'] ?? $payload['domain'] ?? $domain),
        'registrar' => $registrar,
        'created' => normalizeDateString(extractEventDate($result['events'] ?? [], ['registration', 'registered'])),
        'expires' => normalizeDateString(extractEventDate($result['events'] ?? [], ['expiration', 'expiry', 'expires'])),
        'updated' => normalizeDateString(extractEventDate($result['events'] ?? [], ['last changed', 'last update of rdap database', 'updated'])),
        'status' => implode(', ', $statuses),
        'nameservers' => $nameservers,
    ];
}

$domain = normalizeDomain($_GET['domain'] ?? '');
if (!isValidDomain($domain)) {
    sendJson(['error' => 'Invalid domain parameter'], 400);
}

$response = fetchWhoGaJson($domain);
if (!$response['ok'] && !is_array($response['data'])) {
    sendJson(['error' => 'WHOIS API query failed'], 500);
}

$result = normalizeWhoGaResponse($domain, $response['data']);
if (isset($result['error'])) {
    sendJson(['error' => 'WHOIS API query failed'], 500);
}

sendJson($result);
