<?php
declare(strict_types=1);

const DATA_DIR = __DIR__ . '/../data';

function data_path(string $file): string
{
    return DATA_DIR . '/' . $file;
}

function read_json(string $file, $default = [])
{
    $path = data_path($file);
    if (!file_exists($path)) {
        return $default;
    }

    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function write_json(string $file, $data): void
{
    $path = data_path($file);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents($path, $json . PHP_EOL, LOCK_EX);
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalize_domain(string $domain): string
{
    return strtolower(trim($domain));
}

function is_valid_domain_name(string $domain): bool
{
    if ($domain === '' || str_contains($domain, '..')) {
        return false;
    }
    return (bool)preg_match('/^[a-z0-9][a-z0-9.-]{1,251}[a-z0-9]$/i', $domain);
}

function normalize_suffix(string $suffix): string
{
    $suffix = strtolower(trim($suffix));
    if ($suffix === '') {
        return '';
    }
    if ($suffix[0] !== '.') {
        $suffix = '.' . $suffix;
    }
    return $suffix;
}

function is_valid_suffix(string $suffix): bool
{
    if ($suffix === '' || str_contains($suffix, '..')) {
        return false;
    }
    return (bool)preg_match('/^\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/i', $suffix);
}

function is_valid_http_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function parse_date_string(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    try {
        $dt = new DateTime($value);
        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

function parse_whois_raw(string $raw): array
{
    $result = [
        'registrar' => null,
        'reg_date' => null,
        'exp_date' => null,
    ];

    $lines = preg_split('/\r\n|\r|\n/', $raw);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if ($result['registrar'] === null) {
            $patterns = [
                '/Registrar:\s*(.+)/i',
                '/Sponsoring Registrar:\s*(.+)/i',
                '/Registrar Name:\s*(.+)/i',
                '/registrar:\s*(.+)/i',
                '/注册商:\s*(.+)/u',
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $result['registrar'] = trim($matches[1]);
                    break;
                }
            }
        }

        if ($result['reg_date'] === null) {
            $patterns = [
                '/Creation Date:\s*(.+)/i',
                '/Created On:\s*(.+)/i',
                '/Registered On:\s*(.+)/i',
                '/Registration Date:\s*(.+)/i',
                '/Domain Registration Date:\s*(.+)/i',
                '/Registration Time:\s*(.+)/i',
                '/创建时间:\s*(.+)/u',
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $result['reg_date'] = parse_date_string($matches[1]);
                    break;
                }
            }
        }

        if ($result['exp_date'] === null) {
            $patterns = [
                '/Registry Expiry Date:\s*(.+)/i',
                '/Expiration Date:\s*(.+)/i',
                '/Expiry Date:\s*(.+)/i',
                '/paid-till:\s*(.+)/i',
                '/Expiration Time:\s*(.+)/i',
                '/到期时间:\s*(.+)/u',
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $result['exp_date'] = parse_date_string($matches[1]);
                    break;
                }
            }
        }
    }

    return $result;
}

function compute_years(?string $regDate, ?string $expDate): ?int
{
    if (!$regDate || !$expDate) {
        return null;
    }

    try {
        $reg = new DateTime($regDate);
        $exp = new DateTime($expDate);
        $days = (int)$reg->diff($exp)->days;
        if ($days <= 0) {
            return null;
        }
        $years = (int)round($days / 365);
        return max(1, $years);
    } catch (Exception $e) {
        return null;
    }
}

function get_year_badge_label(?int $years): string
{
    if ($years === null || $years <= 0) {
        return '';
    }
    return $years . '年';
}

function get_year_badge_class(?int $years): string
{
    if ($years === null || $years <= 0) {
        return '';
    }
    if ($years >= 20) {
        return 'year-badge-20plus';
    }
    if ($years >= 10) {
        return 'year-badge-10to19';
    }
    if ($years >= 5) {
        return 'year-badge-5to9';
    }
    if ($years >= 3) {
        return 'year-badge-3to4';
    }
    return 'year-badge-1to2';
}

function ensure_admin_config(): void
{
    $path = data_path('admin.json');
    if (file_exists($path)) {
        $data = read_json('admin.json', []);
        if (!empty($data['password_hash'])) {
            return;
        }
    }

    $data = [
        'username' => 'admin',
        'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
    ];
    write_json('admin.json', $data);
}

function verify_admin_login(string $username, string $password): bool
{
    $config = read_json('admin.json', []);
    $storedUser = $config['username'] ?? '';
    $hash = $config['password_hash'] ?? '';

    if ($username !== $storedUser || $hash === '') {
        return false;
    }

    return password_verify($password, $hash);
}

function ensure_mail_config(): void
{
    $path = data_path('mail.json');
    if (file_exists($path)) {
        return;
    }

    $default = [
        'provider' => 'none',
        'from_email' => 'noreply@domain.ls',
        'from_name' => 'DOMAIN.LS',
        'to_email' => 'contact@domain.ls',
        'resend' => [
            'api_key' => '',
        ],
        'smtp' => [
            'host' => '',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => '',
            'password' => '',
        ],
    ];

    write_json('mail.json', $default);
}

function get_mail_config(): array
{
    ensure_mail_config();
    return read_json('mail.json', []);
}

function send_contact_email(array $payload, ?string &$error = null): bool
{
    $config = get_mail_config();
    $provider = strtolower((string)($config['provider'] ?? 'none'));
    $fromEmail = trim((string)($config['from_email'] ?? 'noreply@domain.ls'));
    $fromName = trim((string)($config['from_name'] ?? 'DOMAIN.LS'));
    $toEmail = trim((string)($config['to_email'] ?? 'contact@domain.ls'));

    $subject = trim((string)($payload['subject'] ?? '域名咨询'));
    $content = [
        '姓名：' . (string)($payload['name'] ?? ''),
        '邮箱：' . (string)($payload['email'] ?? ''),
        '域名：' . (string)($payload['domain'] ?? ''),
        '内容：',
        (string)($payload['message'] ?? ''),
    ];
    $textBody = implode("\n", $content);
    $htmlBody = nl2br(h($textBody));

    if ($provider === 'none') {
        return true;
    }

    if ($provider === 'resend') {
        $apiKey = (string)($config['resend']['api_key'] ?? '');
        if ($apiKey === '') {
            $error = 'Resend API Key 未配置';
            return false;
        }
        return send_via_resend($apiKey, $fromEmail, $fromName, $toEmail, $subject, $htmlBody, $error);
    }

    if ($provider === 'smtp') {
        $smtp = $config['smtp'] ?? [];
        return send_via_smtp($smtp, $fromEmail, $fromName, $toEmail, $subject, $textBody, $error);
    }

    $error = '未知邮件通道';
    return false;
}

function send_via_resend(string $apiKey, string $fromEmail, string $fromName, string $toEmail, string $subject, string $htmlBody, ?string &$error = null): bool
{
    $payload = [
        'from' => $fromName . ' <' . $fromEmail . '>',
        'to' => [$toEmail],
        'subject' => $subject,
        'html' => $htmlBody,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            $error = 'Resend 请求失败：' . $curlError;
            return false;
        }

        if ($status < 200 || $status >= 300) {
            $error = 'Resend 响应错误：' . $status;
            return false;
        }

        return true;
    }

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$apiKey}\r\nContent-Type: application/json\r\n",
            'content' => $json,
            'timeout' => 20,
        ],
    ];
    $context = stream_context_create($opts);
    $result = @file_get_contents('https://api.resend.com/emails', false, $context);
    if ($result === false) {
        $error = 'Resend 请求失败';
        return false;
    }
    return true;
}

function send_via_smtp(array $smtp, string $fromEmail, string $fromName, string $toEmail, string $subject, string $textBody, ?string &$error = null): bool
{
    $host = (string)($smtp['host'] ?? '');
    $port = (int)($smtp['port'] ?? 465);
    $encryption = strtolower((string)($smtp['encryption'] ?? 'ssl'));
    $username = (string)($smtp['username'] ?? '');
    $password = (string)($smtp['password'] ?? '');

    if ($host === '' || $username === '' || $password === '') {
        $error = 'SMTP 配置不完整';
        return false;
    }

    $transport = $host;
    if ($encryption === 'ssl') {
        $transport = 'ssl://' . $host;
    }

    $fp = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        $error = 'SMTP 连接失败：' . $errstr;
        return false;
    }
    stream_set_timeout($fp, 20);

    $read = smtp_read($fp);
    if (!smtp_ok($read, [220])) {
        fclose($fp);
        $error = 'SMTP 握手失败';
        return false;
    }

    smtp_write($fp, 'EHLO domain.ls');
    $read = smtp_read($fp);
    if (!smtp_ok($read, [250])) {
        fclose($fp);
        $error = 'SMTP EHLO 失败';
        return false;
    }

    if ($encryption === 'tls') {
        smtp_write($fp, 'STARTTLS');
        $read = smtp_read($fp);
        if (!smtp_ok($read, [220])) {
            fclose($fp);
            $error = 'SMTP STARTTLS 失败';
            return false;
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            $error = 'SMTP TLS 加密失败';
            return false;
        }
        smtp_write($fp, 'EHLO domain.ls');
        $read = smtp_read($fp);
        if (!smtp_ok($read, [250])) {
            fclose($fp);
            $error = 'SMTP TLS-EHLO 失败';
            return false;
        }
    }

    smtp_write($fp, 'AUTH LOGIN');
    if (!smtp_ok(smtp_read($fp), [334])) {
        fclose($fp);
        $error = 'SMTP AUTH 初始化失败';
        return false;
    }

    smtp_write($fp, base64_encode($username));
    if (!smtp_ok(smtp_read($fp), [334])) {
        fclose($fp);
        $error = 'SMTP 用户名认证失败';
        return false;
    }

    smtp_write($fp, base64_encode($password));
    if (!smtp_ok(smtp_read($fp), [235])) {
        fclose($fp);
        $error = 'SMTP 密码认证失败';
        return false;
    }

    smtp_write($fp, 'MAIL FROM:<' . $fromEmail . '>');
    if (!smtp_ok(smtp_read($fp), [250])) {
        fclose($fp);
        $error = 'SMTP 发件人失败';
        return false;
    }

    smtp_write($fp, 'RCPT TO:<' . $toEmail . '>');
    if (!smtp_ok(smtp_read($fp), [250, 251])) {
        fclose($fp);
        $error = 'SMTP 收件人失败';
        return false;
    }

    smtp_write($fp, 'DATA');
    if (!smtp_ok(smtp_read($fp), [354])) {
        fclose($fp);
        $error = 'SMTP DATA 失败';
        return false;
    }

    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'To: <' . $toEmail . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    $body = implode("\r\n", $headers) . "\r\n\r\n" . $textBody . "\r\n.";
    smtp_write($fp, $body);
    if (!smtp_ok(smtp_read($fp), [250])) {
        fclose($fp);
        $error = 'SMTP 投递失败';
        return false;
    }

    smtp_write($fp, 'QUIT');
    fclose($fp);
    return true;
}

function smtp_write($fp, string $command): void
{
    fwrite($fp, $command . "\r\n");
}

function smtp_read($fp): string
{
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function smtp_ok(string $response, array $codes): bool
{
    $code = (int)substr($response, 0, 3);
    return in_array($code, $codes, true);
}
