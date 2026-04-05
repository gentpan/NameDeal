<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';

if (is_admin_logged_in()) {
    header('Location: /admin/domains.php');
    exit;
}

$content = read_json('content.json', []);
$siteSettings = is_array($content['site_settings'] ?? null) ? $content['site_settings'] : [];
$adminBrandName = trim((string)($siteSettings['site_name'] ?? '')) ?: '://domain.ls';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (verify_admin_login($username, $password)) {
        admin_login();
        header('Location: /admin/domains.php');
        exit;
    }

    $error = '账号或密码错误';
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($adminBrandName); ?> 后台登录</title>
    <link rel="stylesheet" href="https://icons.bluecdn.com/fontawesome-pro/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-login-wrap">
    <div class="admin-login-card">
        <div class="admin-login-brand">
            <div class="admin-login-logo"><?php echo h($adminBrandName); ?></div>
            <div class="admin-login-brand-line"></div>
        </div>
        <div class="admin-login-head">
            <div class="admin-login-title"><i class="fa-light fa-shield-keyhole"></i> 管理后台登录</div>
            <div class="admin-login-sub">请输入管理员账号与密码继续访问。</div>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger mb-3"><?php echo h($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <?php echo csrf_input(); ?>
            <div class="mb-3">
                <label class="form-label">账号</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">密码</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100" type="submit"><i class="fa-light fa-right-to-bracket"></i> 登录</button>
        </form>
    </div>
</div>
</body>
</html>
