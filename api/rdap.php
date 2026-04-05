<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$domain = normalize_domain((string)($_GET['domain'] ?? ''));
if ($domain === '' || !preg_match('/^[a-z0-9][a-z0-9.-]{1,251}[a-z0-9]$/i', $domain) || str_contains($domain, '..')) {
    echo json_encode([
        'success' => false,
        'message' => '请输入有效域名，例如 domain.ls',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function load_local_rdap(string $domain): ?array
{
    $samples = read_json('rdap_samples.json', []);
    if (isset($samples[$domain]) && is_array($samples[$domain])) {
        $row = $samples[$domain];
        $row['domain'] = $row['domain'] ?? $domain;
        return $row;
    }

    $domains = read_json('domains.json', []);
    foreach ($domains as $item) {
        if (normalize_domain((string)($item['domain'] ?? '')) !== $domain) {
            continue;
        }

        return [
            'domain' => $domain,
            'handle' => '',
            'port43' => 'whois.nic.ls',
            'registrar' => (string)($item['registrar'] ?? 'Local Registry'),
            'status' => ['active'],
            'nameservers' => ['ns1.domain.ls', 'ns2.domain.ls'],
            'events' => [
                'registration' => (string)($item['reg_date'] ?? ''),
                'expiration' => (string)($item['exp_date'] ?? ''),
                'last_changed' => date('Y-m-d'),
            ],
            'raw' => [
                'source' => 'domains.json',
                'domain' => $domain,
                'reg_date' => (string)($item['reg_date'] ?? ''),
                'exp_date' => (string)($item['exp_date'] ?? ''),
            ],
        ];
    }

    return null;
}

$url = 'https://rdap.org/domain/' . rawurlencode($domain);

$responseBody = null;
$status = 0;
$error = '';

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'],
    ]);
    $responseBody = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($responseBody === false) {
        $error = curl_error($ch);
    }
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => "Accept: application/rdap+json, application/json\r\n",
        ],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $headers = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?: [])
        : [];
    $statusLine = is_array($headers) && isset($headers[0]) ? (string)$headers[0] : '';
    $status = (int)preg_replace('/[^0-9]/', '', explode(' ', $statusLine)[1] ?? '0');
    if ($responseBody === false) {
        $error = '请求 RDAP 失败';
    }
}

if (!$responseBody || $status >= 400 || $error !== '') {
    $local = load_local_rdap($domain);
    if (is_array($local)) {
        echo json_encode([
            'success' => true,
            'source' => 'local',
            'data' => $local,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => ($error !== '' ? $error : 'RDAP 查询失败') . '，且未找到本地样本数据',
        'status' => $status,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$data = json_decode($responseBody, true);
if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'RDAP 返回数据解析失败',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$events = $data['events'] ?? [];
$eventMap = [];
foreach ($events as $event) {
    $name = strtolower((string)($event['eventAction'] ?? ''));
    $date = (string)($event['eventDate'] ?? '');
    if ($name !== '' && $date !== '' && !isset($eventMap[$name])) {
        $eventMap[$name] = $date;
    }
}

$nameservers = [];
foreach (($data['nameservers'] ?? []) as $ns) {
    $ldh = (string)($ns['ldhName'] ?? '');
    if ($ldh !== '') {
        $nameservers[] = $ldh;
    }
}

$statuses = array_values(array_filter(array_map('strval', $data['status'] ?? [])));

$registrar = '';
foreach (($data['entities'] ?? []) as $entity) {
    $roles = $entity['roles'] ?? [];
    if (in_array('registrar', $roles, true)) {
        $vcard = $entity['vcardArray'][1] ?? [];
        foreach ($vcard as $item) {
            if (($item[0] ?? '') === 'fn') {
                $registrar = (string)($item[3] ?? '');
                break;
            }
        }
        if ($registrar !== '') {
            break;
        }
    }
}

$result = [
    'domain' => (string)($data['ldhName'] ?? $domain),
    'handle' => (string)($data['handle'] ?? ''),
    'port43' => (string)($data['port43'] ?? ''),
    'registrar' => $registrar,
    'status' => $statuses,
    'nameservers' => $nameservers,
    'events' => [
        'registration' => $eventMap['registration'] ?? ($eventMap['created'] ?? ''),
        'expiration' => $eventMap['expiration'] ?? ($eventMap['expiry'] ?? ''),
        'last_changed' => $eventMap['last changed'] ?? ($eventMap['last update of rdap database'] ?? ''),
    ],
    'raw' => $data,
];

echo json_encode([
    'success' => true,
    'source' => 'rdap',
    'data' => $result,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
