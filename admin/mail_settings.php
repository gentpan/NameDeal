<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
require_admin();
require __DIR__ . '/_layout.php';

ensure_mail_config();
$config = get_mail_config();
$flash = [
    'saved' => false,
    'test_success' => false,
    'test_error' => '',
    'error' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = (string)($_POST['action'] ?? 'save');
    $provider = strtolower(trim((string)($_POST['provider'] ?? 'none')));
    if (!in_array($provider, ['none', 'resend', 'smtp'], true)) {
        $provider = 'none';
    }

    $config = [
        'provider' => $provider,
        'from_email' => trim((string)($_POST['from_email'] ?? 'noreply@domain.ls')),
        'from_name' => trim((string)($_POST['from_name'] ?? 'DOMAIN.LS')),
        'to_email' => trim((string)($_POST['to_email'] ?? 'contact@domain.ls')),
        'resend' => [
            'api_key' => trim((string)($_POST['resend_api_key'] ?? '')),
        ],
        'smtp' => [
            'host' => trim((string)($_POST['smtp_host'] ?? '')),
            'port' => (int)($_POST['smtp_port'] ?? 465),
            'encryption' => strtolower(trim((string)($_POST['smtp_encryption'] ?? 'ssl'))),
            'username' => trim((string)($_POST['smtp_username'] ?? '')),
            'password' => trim((string)($_POST['smtp_password'] ?? '')),
        ],
    ];

    $fromEmail = (string)($config['from_email'] ?? '');
    $toEmail = (string)($config['to_email'] ?? '');
    $smtpPort = (int)($config['smtp']['port'] ?? 0);
    $smtpEncryption = (string)($config['smtp']['encryption'] ?? 'ssl');

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $flash['error'] = '发件邮箱格式无效';
    } elseif (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $flash['error'] = '收件邮箱格式无效';
    } elseif ($smtpPort < 1 || $smtpPort > 65535) {
        $flash['error'] = 'SMTP 端口范围必须在 1-65535';
    } elseif (!in_array($smtpEncryption, ['ssl', 'tls', 'none'], true)) {
        $flash['error'] = 'SMTP 加密方式无效';
    } elseif ($provider === 'smtp') {
        $smtpHost = trim((string)($config['smtp']['host'] ?? ''));
        $smtpUser = trim((string)($config['smtp']['username'] ?? ''));
        $smtpPass = (string)($config['smtp']['password'] ?? '');
        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '') {
            $flash['error'] = 'SMTP 模式下 Host/用户名/密码不能为空';
        }
    }

    if ($flash['error'] === '') {
        write_json('mail.json', $config);
    }

    if ($flash['error'] !== '') {
        // Skip send test when config itself is invalid.
    } elseif ($action === 'test') {
        if (($config['provider'] ?? 'none') === 'none') {
            $flash['test_error'] = '当前发送通道为“仅保存留言（不发邮件）”，请先切换到 Resend 或 SMTP。';
        } else {
            $testTo = trim((string)($_POST['test_to_email'] ?? ''));
            if ($testTo === '' || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
                $flash['test_error'] = '测试收件邮箱格式无效';
            } else {
                $subject = trim((string)($_POST['test_subject'] ?? '后台邮件测试'));
                $message = trim((string)($_POST['test_message'] ?? '这是一封来自 DOMAIN.LS 后台的测试邮件。'));
                $originalTo = $config['to_email'] ?? '';
                $config['to_email'] = $testTo;
                write_json('mail.json', $config);

                $sendError = null;
                $ok = send_contact_email([
                    'name' => 'System Test',
                    'email' => $config['from_email'] ?? 'noreply@domain.ls',
                    'subject' => $subject,
                    'message' => $message,
                    'domain' => 'domain.ls',
                ], $sendError);

                $config['to_email'] = $originalTo;
                write_json('mail.json', $config);

                if ($ok) {
                    $flash['test_success'] = true;
                } else {
                    $flash['test_error'] = '测试发送失败：' . ($sendError ?: '未知错误');
                }
            }
        }
    } else {
        $flash['saved'] = true;
    }
}

admin_header('邮件配置');
?>
<div class="admin-card">
    <?php if ($flash['saved']): ?>
        <div class="alert alert-success">邮件配置已保存</div>
    <?php endif; ?>
    <?php if ($flash['test_success']): ?>
        <div class="alert alert-success">测试邮件发送成功，请检查收件箱。</div>
    <?php endif; ?>
    <?php if ($flash['test_error'] !== ''): ?>
        <div class="alert alert-danger"><?php echo h($flash['test_error']); ?></div>
    <?php endif; ?>
    <?php if ($flash['error'] !== ''): ?>
        <div class="alert alert-danger"><?php echo h($flash['error']); ?></div>
    <?php endif; ?>

    <form method="post">
        <?php echo csrf_input(); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 mb-0">发送通道配置</h2>
            <span class="badge text-bg-secondary">Mail Gateway</span>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">发送通道</label>
                <select class="form-select" name="provider">
                    <?php $provider = $config['provider'] ?? 'none'; ?>
                    <option value="none" <?php echo $provider === 'none' ? 'selected' : ''; ?>>仅保存留言（不发邮件）</option>
                    <option value="resend" <?php echo $provider === 'resend' ? 'selected' : ''; ?>>Resend API</option>
                    <option value="smtp" <?php echo $provider === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">发件邮箱</label>
                <input class="form-control" type="email" name="from_email" value="<?php echo h($config['from_email'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">发件名称</label>
                <input class="form-control" type="text" name="from_name" value="<?php echo h($config['from_name'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">收件邮箱</label>
                <input class="form-control" type="email" name="to_email" value="<?php echo h($config['to_email'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Resend API Key</label>
                <input class="form-control" type="text" name="resend_api_key" value="<?php echo h($config['resend']['api_key'] ?? ''); ?>" placeholder="re_xxx">
            </div>

            <div class="col-12"><hr></div>
            <div class="col-md-6">
                <label class="form-label">SMTP Host</label>
                <input class="form-control" type="text" name="smtp_host" value="<?php echo h($config['smtp']['host'] ?? ''); ?>" placeholder="smtp.example.com">
            </div>
            <div class="col-md-2">
                <label class="form-label">Port</label>
                <input class="form-control" type="number" name="smtp_port" value="<?php echo h((string)($config['smtp']['port'] ?? 465)); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">加密</label>
                <?php $enc = $config['smtp']['encryption'] ?? 'ssl'; ?>
                <select class="form-select" name="smtp_encryption">
                    <option value="ssl" <?php echo $enc === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="tls" <?php echo $enc === 'tls' ? 'selected' : ''; ?>>TLS (STARTTLS)</option>
                    <option value="none" <?php echo $enc === 'none' ? 'selected' : ''; ?>>无</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">SMTP 用户名</label>
                <input class="form-control" type="text" name="smtp_username" value="<?php echo h($config['smtp']['username'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">SMTP 密码</label>
                <input class="form-control" type="password" name="smtp_password" value="<?php echo h($config['smtp']['password'] ?? ''); ?>">
            </div>

            <div class="col-12 mt-2"><hr></div>
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">邮件测试</h2>
                <span class="badge text-bg-info">Test</span>
            </div>
            <div class="col-md-4">
                <label class="form-label">测试收件邮箱</label>
                <input class="form-control" type="email" name="test_to_email" placeholder="you@example.com" value="<?php echo h($config['to_email'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">测试主题</label>
                <input class="form-control" type="text" name="test_subject" value="后台邮件测试 - DOMAIN.LS">
            </div>
            <div class="col-md-4">
                <label class="form-label">测试内容</label>
                <input class="form-control" type="text" name="test_message" value="这是一封来自 DOMAIN.LS 后台的测试邮件。">
            </div>
        </div>

        <div class="mt-4 d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit" name="action" value="save">保存配置</button>
            <button class="btn btn-outline-primary" type="submit" name="action" value="test">保存并测试发送</button>
        </div>
    </form>
</div>
<?php
admin_footer();
