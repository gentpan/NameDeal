<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/helpers.php';

ensure_admin_config();

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        header('Location: /admin/index.php');
        exit;
    }

    require_password_update();
}

function admin_login(): void
{
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    $token = $_SESSION['_csrf_token'] ?? '';
    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
    }
    return $token;
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool
{
    $sessionToken = $_SESSION['_csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }
    if (!is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

function require_csrf(): void
{
    $token = $_POST['_csrf'] ?? null;
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        http_response_code(403);
        echo 'CSRF 验证失败，请刷新页面后重试。';
        exit;
    }
}

function admin_uses_default_password(): bool
{
    $config = read_json('admin.json', []);
    $username = (string)($config['username'] ?? '');
    $hash = (string)($config['password_hash'] ?? '');
    if ($username !== 'admin' || $hash === '') {
        return false;
    }
    return password_verify('admin123', $hash);
}

function require_password_update(): void
{
    if (!admin_uses_default_password()) {
        return;
    }

    $current = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $allow = ['site_settings.php', 'security.php', 'logout.php', 'index.php'];
    if (!in_array($current, $allow, true)) {
        header('Location: /admin/site_settings.php?force=1');
        exit;
    }
}

function update_admin_password(string $currentPassword, string $newPassword, ?string &$error = null): bool
{
    $config = read_json('admin.json', []);
    $hash = (string)($config['password_hash'] ?? '');
    if ($hash === '' || !password_verify($currentPassword, $hash)) {
        $error = '当前密码错误';
        return false;
    }

    $newPassword = trim($newPassword);
    if (strlen($newPassword) < 10) {
        $error = '新密码至少 10 位';
        return false;
    }

    $config['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    write_json('admin.json', $config);
    return true;
}
