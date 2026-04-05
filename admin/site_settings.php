<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
require_admin();
require __DIR__ . '/_layout.php';

$content = read_json('content.json', []);
$site = $content['site_settings'] ?? [];

$defaults = [
    'site_name' => '://domain.ls',
    'logo_subtitle' => 'PREMIUM DOMAIN COLLECTION',
    'site_title' => 'domain.ls - 域名列表',
    'site_description' => '精品域名展示与交易咨询',
];

$settings = array_merge($defaults, is_array($site) ? $site : []);
$forceMode = isset($_GET['force']) && $_GET['force'] === '1' && admin_uses_default_password();
$siteFlash = '';
$siteError = '';
$securityFlash = '';
$securityError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? 'site');

    if ($action === 'security') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($newPassword !== $confirmPassword) {
            $securityError = '两次输入的新密码不一致';
        } else {
            $updateError = null;
            if (update_admin_password($currentPassword, $newPassword, $updateError)) {
                $securityFlash = '管理员密码已更新';
            } else {
                $securityError = $updateError ?: '更新失败';
            }
        }
    } else {
        $siteName = trim((string)($_POST['site_name'] ?? ''));
        $logoSubtitle = trim((string)($_POST['logo_subtitle'] ?? ''));
        $siteTitle = trim((string)($_POST['site_title'] ?? ''));
        $siteDescription = trim((string)($_POST['site_description'] ?? ''));

        if ($siteName === '') {
            $siteError = '站点名称不能为空';
        } elseif ($siteTitle === '') {
            $siteError = '浏览器标题不能为空';
        } else {
            $settings['site_name'] = $siteName;
            $settings['logo_subtitle'] = $logoSubtitle;
            $settings['site_title'] = $siteTitle;
            $settings['site_description'] = $siteDescription;

            if (!isset($content['site_settings']) || !is_array($content['site_settings'])) {
                $content['site_settings'] = [];
            }
            $content['site_settings'] = array_merge($content['site_settings'], $settings);
            write_json('content.json', $content);
            $siteFlash = '站点设置已保存';
        }
    }
}

admin_header('站点设置');
?>
<div class="admin-card">
    <?php if ($forceMode): ?>
        <div class="alert alert-warning">检测到你仍在使用默认密码，请先在下方“安全设置”中修改密码。</div>
    <?php endif; ?>
    <?php if ($siteFlash !== ''): ?>
        <div class="alert alert-success"><?php echo h($siteFlash); ?></div>
    <?php endif; ?>
    <?php if ($siteError !== ''): ?>
        <div class="alert alert-danger"><?php echo h($siteError); ?></div>
    <?php endif; ?>

    <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="site">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">站点名称（左侧 Logo 文案）</label>
                <input class="form-control" type="text" name="site_name" value="<?php echo h((string)$settings['site_name']); ?>" placeholder="://domain.ls" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">站点副标题（右侧）</label>
                <input class="form-control" type="text" name="logo_subtitle" value="<?php echo h((string)$settings['logo_subtitle']); ?>" placeholder="PREMIUM DOMAIN COLLECTION">
            </div>
            <div class="col-md-8">
                <label class="form-label">浏览器标题（title）</label>
                <input class="form-control" type="text" name="site_title" value="<?php echo h((string)$settings['site_title']); ?>" placeholder="domain.ls - 域名列表" required>
            </div>
            <div class="col-md-12">
                <label class="form-label">站点描述（meta description）</label>
                <input class="form-control" type="text" name="site_description" value="<?php echo h((string)$settings['site_description']); ?>" placeholder="精品域名展示与交易咨询">
            </div>
        </div>
        <div class="mt-4">
            <button class="btn btn-primary" type="submit">保存设置</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <?php if ($securityFlash !== ''): ?>
        <div class="alert alert-success"><?php echo h($securityFlash); ?></div>
    <?php endif; ?>
    <?php if ($securityError !== ''): ?>
        <div class="alert alert-danger"><?php echo h($securityError); ?></div>
    <?php endif; ?>

    <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="security">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">当前密码</label>
                <input class="form-control" type="password" name="current_password" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">新密码</label>
                <input class="form-control" type="password" name="new_password" minlength="10" required>
                <div class="form-text">建议使用大写/小写/数字/符号组合，至少 10 位。</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">确认新密码</label>
                <input class="form-control" type="password" name="confirm_password" minlength="10" required>
            </div>
        </div>
        <div class="mt-4">
            <button class="btn btn-primary" type="submit">更新密码</button>
        </div>
    </form>
</div>
<?php
admin_footer();
