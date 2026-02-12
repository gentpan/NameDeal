<?php

/**
 * 域名管理后台
 * 提供域名配置的增删改查功能
 */

// 读取JSON文件辅助函数
function readJsonFile($file, $default = [])
{
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: $default;
    }
    return $default;
}

// 写入JSON文件辅助函数
function writeJsonFile($file, $data)
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function getSiteSettingsFile()
{
    return __DIR__ . '/data/site_settings.json';
}

function getSiteSettings()
{
    return readJsonFile(getSiteSettingsFile());
}

function verifyAdminPassword($inputPassword, &$shouldMigrate = false)
{
    $settings = getSiteSettings();
    $passwordHash = $settings['admin_password_hash'] ?? '';
    $legacyPassword = $settings['admin_password'] ?? '12345678';

    if (!empty($passwordHash)) {
        return password_verify($inputPassword, $passwordHash);
    }

    $isValid = hash_equals((string)$legacyPassword, (string)$inputPassword);
    $shouldMigrate = $isValid;
    return $isValid;
}

function migrateLegacyPasswordToHash($plainPassword)
{
    $settings = getSiteSettings();
    $settings['admin_password_hash'] = password_hash($plainPassword, PASSWORD_DEFAULT);
    unset($settings['admin_password']);
    writeJsonFile(getSiteSettingsFile(), $settings);
}

function ensureCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token)
{
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function isValidDomainName($domain)
{
    return (bool)preg_match('/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/i', $domain);
}

function isValidHttpUrl($url)
{
    if ($url === '') {
        return true;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function getDefaultFooterLinks()
{
    return [
        ['name' => 'WHOIS查询', 'url' => 'https://bluewhois.com/{domain}', 'icon_class' => 'fa-solid fa-magnifying-glass'],
        ['name' => '西风', 'url' => 'https://xifeng.net', 'icon_class' => 'fa-solid fa-wind'],
        ['name' => '更多域名', 'url' => 'https://domain.ls', 'icon_class' => 'fa-solid fa-globe'],
    ];
}

function normalizeFooterLinks($rawLinks)
{
    $defaultLinks = getDefaultFooterLinks();
    if (!is_array($rawLinks) || empty($rawLinks)) {
        return $defaultLinks;
    }

    $normalized = [];
    foreach ($rawLinks as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string)($item['name'] ?? ''));
        $url = trim((string)($item['url'] ?? ''));
        $iconClass = trim((string)($item['icon_class'] ?? 'fa-solid fa-link'));

        if ($name === '' || $url === '') {
            continue;
        }

        $normalized[] = [
            'name' => $name,
            'url' => $url,
            'icon_class' => $iconClass,
        ];
    }

    if (empty($normalized)) {
        return $defaultLinks;
    }
    return array_slice($normalized, 0, 3);
}

function getFooterLinksFromSettings($data)
{
    if (!empty($data['footer_links']) && is_array($data['footer_links'])) {
        return normalizeFooterLinks($data['footer_links']);
    }

    return normalizeFooterLinks([
        [
            'name' => 'WHOIS查询',
            'url' => $data['footer_whois_url'] ?? 'https://bluewhois.com/{domain}',
            'icon_class' => 'fa-solid fa-magnifying-glass',
        ],
        [
            'name' => '西风',
            'url' => $data['footer_xifeng_url'] ?? 'https://xifeng.net',
            'icon_class' => 'fa-solid fa-wind',
        ],
        [
            'name' => '更多域名',
            'url' => $data['footer_more_domains_url'] ?? 'https://domain.ls',
            'icon_class' => 'fa-solid fa-globe',
        ],
    ]);
}

function isValidFooterLinkUrl($url)
{
    $normalizedUrl = str_replace('{domain}', 'example.com', (string)$url);
    return isValidHttpUrl($normalizedUrl);
}

function isValidFontAwesomeIconClass($iconClass)
{
    if ($iconClass === '') {
        return false;
    }
    // 支持 FontAwesome 类名
    if (preg_match('/^(fa-(solid|regular|brands|duotone|thin|light)\s+)?fa-[a-z0-9-]+(?:\s+fa-[a-z0-9-]+)*$/i', $iconClass)) {
        return true;
    }
    // 支持 SVG 代码（必须以 <svg 开头，以 </svg> 结尾）
    if (isValidSvgIcon($iconClass)) {
        return true;
    }
    return false;
}

function isValidSvgIcon($str)
{
    $str = trim($str);
    if (stripos($str, '<svg') !== 0 || stripos($str, '</svg>') === false) {
        return false;
    }
    // 禁止脚本注入
    $lower = strtolower($str);
    if (preg_match('/<script|on\w+\s*=|javascript:/i', $lower)) {
        return false;
    }
    return true;
}

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
}

session_start();
$csrfToken = ensureCsrfToken();
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$adminEntryUrl = '/admin';

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $loginError = '请求无效，请刷新页面后重试';
    } elseif (!isset($_POST['password']) || $_POST['password'] === '') {
        $loginError = '请输入密码';
    } else {
        $shouldMigratePassword = false;
        if (verifyAdminPassword($_POST['password'], $shouldMigratePassword)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            if ($shouldMigratePassword) {
                migrateLegacyPasswordToHash($_POST['password']);
            }
            header('Location: ' . $adminEntryUrl);
            exit;
        }
        $loginError = '密码错误';
    }
}

// 处理登出
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $adminEntryUrl);
    exit;
}

// 如果未登录，显示登录表单
if (!$isLoggedIn) {
?>
    <!DOCTYPE html>
    <html lang="zh-CN">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>域名管理后台 - 登录</title>
        <link rel="stylesheet" href="/assets/css/admin.css">
    </head>

    <body class="login-page">
        <div class="login-box">
            <h1>
                <svg class="login-icon" viewBox="0 0 24 24" fill="currentColor" width="24" height="24" style="vertical-align: middle; margin-right: 8px;">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" />
                </svg>
                域名管理后台
            </h1>
            <?php if (isset($loginError)): ?>
                <div class="error"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="password">管理员密码</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit">登录</button>
            </form>
        </div>
    </body>

    </html>
<?php
    exit;
}

// 已登录，处理AJAX请求
require_once __DIR__ . '/core/DomainManager.php';
require_once __DIR__ . '/core/StatsTracker.php';
require_once __DIR__ . '/core/DomainConfig.php';
require_once __DIR__ . '/core/EmailHandler.php';
$domainManager = new DomainManager();

// 处理AJAX请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => '请求无效，请刷新页面后重试'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    switch ($_POST['action']) {
        case 'get_email_settings':
            $settingsFile = __DIR__ . '/data/email_settings.json';
            $data = readJsonFile($settingsFile);
            echo json_encode(['success' => true, 'data' => [
                'from_name' => $data['from_name'] ?? '',
                'from_email' => $data['from_email'] ?? '',
                'default_to_email' => $data['default_to_email'] ?? '',
                'smtp_enabled' => true,
                'smtp_host' => $data['smtp_host'] ?? '',
                'smtp_port' => (int)($data['smtp_port'] ?? 587),
                'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
                'smtp_username' => $data['smtp_username'] ?? '',
                'smtp_password' => $data['smtp_password'] ?? ''
            ]]);
            exit;

        case 'save_email_settings':
            $fromName = trim($_POST['from_name'] ?? '');
            $fromEmail = trim($_POST['from_email'] ?? '');
            $defaultToEmail = trim($_POST['default_to_email'] ?? '');
            $smtpEnabled = true;
            $smtpHost = trim($_POST['smtp_host'] ?? '');
            $smtpPort = (int)($_POST['smtp_port'] ?? 587);
            $smtpEncryption = in_array($_POST['smtp_encryption'] ?? 'tls', ['none', 'ssl', 'tls']) ? ($_POST['smtp_encryption']) : 'tls';
            $smtpUsername = trim($_POST['smtp_username'] ?? '');
            $smtpPassword = trim($_POST['smtp_password'] ?? '');

            if ($fromEmail && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => '发件人邮箱格式不正确']);
                exit;
            }
            if ($defaultToEmail && !filter_var($defaultToEmail, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => '默认收件邮箱格式不正确']);
                exit;
            }
            $settingsFile = __DIR__ . '/data/email_settings.json';
            writeJsonFile($settingsFile, [
                'from_name' => $fromName,
                'from_email' => $fromEmail,
                'default_to_email' => $defaultToEmail,
                'smtp_enabled' => true,
                'smtp_host' => $smtpHost,
                'smtp_port' => $smtpPort,
                'smtp_encryption' => $smtpEncryption,
                'smtp_username' => $smtpUsername,
                'smtp_password' => $smtpPassword
            ]);
            echo json_encode(['success' => true, 'message' => '邮件设置已保存']);
            exit;

        case 'get_site_settings':
            $settingsFile = __DIR__ . '/data/site_settings.json';
            $data = readJsonFile($settingsFile);
            // 不返回密码，只返回是否存在密码的标记
            echo json_encode(['success' => true, 'data' => [
                'admin_password_set' => !empty($data['admin_password_hash']) || !empty($data['admin_password']),
                'site_name' => $data['site_name'] ?? '',
            ]]);
            exit;

        case 'save_site_settings':
            $oldPassword = trim($_POST['old_password'] ?? '');
            $adminPassword = trim($_POST['admin_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            $siteName = trim($_POST['site_name'] ?? '');

            $settingsFile = __DIR__ . '/data/site_settings.json';
            // 读取现有设置
            $existingData = readJsonFile($settingsFile);

            // 更新设置（先复制现有数据）
            $newData = $existingData;
            $newData['site_name'] = $siteName;

            // 如果要修改密码，必须先验证原密码
            if (!empty($adminPassword)) {
                if (empty($oldPassword)) {
                    echo json_encode(['success' => false, 'message' => '修改密码需要输入原密码']);
                    exit;
                }

                $dummy = false;
                if (!verifyAdminPassword($oldPassword, $dummy)) {
                    echo json_encode(['success' => false, 'message' => '原密码不正确']);
                    exit;
                }

                // 验证新密码
                if (strlen($adminPassword) < 8) {
                    echo json_encode(['success' => false, 'message' => '新密码长度至少8位']);
                    exit;
                }
                if ($adminPassword !== $confirmPassword) {
                    echo json_encode(['success' => false, 'message' => '两次输入的新密码不一致']);
                    exit;
                }
                $newData['admin_password_hash'] = password_hash($adminPassword, PASSWORD_DEFAULT);
                unset($newData['admin_password']);
            }

            writeJsonFile($settingsFile, $newData);
            echo json_encode(['success' => true, 'message' => '站点设置已保存']);
            exit;

        case 'get_footer_settings':
            $settingsFile = __DIR__ . '/data/site_settings.json';
            $data = readJsonFile($settingsFile);
            echo json_encode(['success' => true, 'data' => [
                'footer_links' => getFooterLinksFromSettings($data),
                'footer_analytics_code' => $data['footer_analytics_code'] ?? '',
            ]], JSON_UNESCAPED_UNICODE);
            exit;

        case 'save_footer_settings':
            $settingsFile = __DIR__ . '/data/site_settings.json';
            $existingData = readJsonFile($settingsFile);
            $footerAnalyticsCode = trim((string)($_POST['footer_analytics_code'] ?? ''));
            $footerLinksRaw = (string)($_POST['footer_links_json'] ?? '[]');
            $decoded = json_decode($footerLinksRaw, true);

            if (!is_array($decoded)) {
                echo json_encode(['success' => false, 'message' => '页脚链接数据格式无效'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (count($decoded) > 3) {
                echo json_encode(['success' => false, 'message' => '最多只允许 3 个页脚链接'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $normalizedLinks = [];
            foreach ($decoded as $index => $item) {
                if (!is_array($item)) {
                    echo json_encode(['success' => false, 'message' => '页脚链接格式无效'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $name = trim((string)($item['name'] ?? ''));
                $url = trim((string)($item['url'] ?? ''));
                $iconClass = trim((string)($item['icon_class'] ?? 'fa-solid fa-link'));

                if ($name === '' || $url === '') {
                    echo json_encode(['success' => false, 'message' => '页脚链接名称和 URL 不能为空'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                if (!isValidFooterLinkUrl($url)) {
                    echo json_encode(['success' => false, 'message' => '第 ' . ($index + 1) . ' 个链接 URL 格式不正确'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                if (!isValidFontAwesomeIconClass($iconClass)) {
                    echo json_encode(['success' => false, 'message' => '第 ' . ($index + 1) . ' 个图标类名无效'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $normalizedLinks[] = [
                    'name' => $name,
                    'url' => $url,
                    'icon_class' => $iconClass,
                ];
            }

            if (empty($normalizedLinks)) {
                $normalizedLinks = getDefaultFooterLinks();
            }

            $newData = $existingData;
            $newData['footer_links'] = $normalizedLinks;
            $newData['footer_analytics_code'] = $footerAnalyticsCode;

            writeJsonFile($settingsFile, $newData);
            echo json_encode(['success' => true, 'message' => '页脚设置已保存'], JSON_UNESCAPED_UNICODE);
            exit;

        case 'test_email':
            $to = trim($_POST['to'] ?? '');
            if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => '测试收件邮箱格式不正确']);
                exit;
            }
            // 读取全局发件设置
            $settingsFile = __DIR__ . '/data/email_settings.json';
            $data = readJsonFile($settingsFile);
            $fromName = $data['from_name'] ?? '';
            $fromEmail = $data['from_email'] ?? '';
            // 使用当前域名配置构造 EmailHandler
            $domainConfig = new DomainConfig();
            $emailHandler = new EmailHandler($domainConfig);
            $subject = '测试邮件 - 域名后台';
            $content = '<p>这是一封测试邮件，用于验证邮件发送配置是否可用。</p>';
            $result = $emailHandler->sendEmail($subject, $content, $fromEmail ?: null, $fromName ?: null, null, $to);
            echo json_encode($result);
            exit;

        case 'add':
            // 添加域名
            $domain = DomainConfig::normalizeDomain(trim($_POST['domain'] ?? ''));
            if (empty($domain)) {
                echo json_encode(['success' => false, 'message' => '域名不能为空']);
                exit;
            }

            if (!isValidDomainName($domain)) {
                echo json_encode(['success' => false, 'message' => '域名格式不正确']);
                exit;
            }

            if ($domainManager->domainExists($domain)) {
                echo json_encode(['success' => false, 'message' => '该域名已存在']);
                exit;
            }

            $result = $domainManager->addDomain([
                'domain' => $domain,
                'title' => trim($_POST['title'] ?? $domain),
                'description' => trim($_POST['description'] ?? ''),
                'theme_color' => trim($_POST['theme_color'] ?? '#0065F3'),
                'domain_intro' => trim($_POST['domain_intro'] ?? ''),
                'domain_price' => trim($_POST['domain_price'] ?? '')
            ]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => '添加成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '添加失败']);
            }
            exit;

        case 'update':
            // 更新域名
            if (empty($_POST['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID不能为空']);
                exit;
            }

            $result = $domainManager->updateDomain($_POST['id'], [
                'title' => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'theme_color' => trim($_POST['theme_color'] ?? '#0065F3'),
                'domain_intro' => trim($_POST['domain_intro'] ?? ''),
                'domain_price' => trim($_POST['domain_price'] ?? '')
            ]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => '更新成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '更新失败']);
            }
            exit;

        case 'delete':
            // 删除域名
            if (empty($_POST['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID不能为空']);
                exit;
            }

            $result = $domainManager->deleteDomain($_POST['id']);

            if ($result) {
                echo json_encode(['success' => true, 'message' => '删除成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '删除失败']);
            }
            exit;

        case 'get':
            // 获取单个域名配置
            if (empty($_POST['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID不能为空']);
                exit;
            }

            $domain = $domainManager->getDomainById($_POST['id']);

            if ($domain) {
                echo json_encode(['success' => true, 'data' => $domain]);
            } else {
                echo json_encode(['success' => false, 'message' => '域名不存在']);
            }
            exit;
    }
}

$allowedSections = ['domains', 'stats', 'email', 'site', 'footer'];
// section 切换（支持 ?section= 和 /admin/{section} 两种形式）
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$sectionFromPath = '';
if (preg_match('#^/admin(?:/([a-z]+))?/?$#i', $requestPath, $m)) {
    $sectionFromPath = strtolower((string)($m[1] ?? 'domains'));
}
$section = isset($_GET['section']) ? (string)$_GET['section'] : ($sectionFromPath ?: 'domains');
if (!in_array($section, $allowedSections, true)) {
    $section = 'domains';
}
$usePrettyAdminRoutes = true;
$adminAjaxEndpoint = $usePrettyAdminRoutes ? '/admin.php' : $_SERVER['PHP_SELF'];

function adminSectionUrl($section = 'domains', $params = [])
{
    global $usePrettyAdminRoutes, $allowedSections;

    $targetSection = in_array($section, $allowedSections, true) ? $section : 'domains';
    $query = http_build_query($params);

    if ($usePrettyAdminRoutes) {
        $path = '/admin' . ($targetSection === 'domains' ? '' : '/' . $targetSection);
        return $query !== '' ? ($path . '?' . $query) : $path;
    }

    $base = $_SERVER['PHP_SELF'] . '?section=' . rawurlencode($targetSection);
    return $query !== '' ? ($base . '&' . $query) : $base;
}

function adminLogoutUrl()
{
    global $usePrettyAdminRoutes;
    if ($usePrettyAdminRoutes) {
        return '/admin?logout=1';
    }
    return $_SERVER['PHP_SELF'] . '?logout=1';
}

// 分页设置
$perPage = isset($_GET['per_page']) ? max(10, min(50, (int)$_GET['per_page'])) : 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// 分页计算辅助函数
function getPaginationData($total, $perPage, $currentPage) {
    $totalPages = ceil($total / $perPage);
    $offset = ($currentPage - 1) * $perPage;
    return ['total_pages' => $totalPages, 'offset' => $offset];
}

// 获取所有域名（用于域名管理 & 邮件设置）
$allDomains = $domainManager->getAllDomains();
$domainsCount = count($allDomains);

// 分页处理
if ($section === 'domains') {
    $pagination = getPaginationData($domainsCount, $perPage, $currentPage);
    $totalPages = $pagination['total_pages'];
    $domains = array_slice($allDomains, $pagination['offset'], $perPage);
} else {
    $domains = $allDomains;
    $totalPages = 1;
}

// 若统计页，准备统计数据
$days = 30;
$allStats = [];
$statsTotalPages = 1;
if ($section === 'stats') {
    $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 30;
    $statsTracker = new StatsTracker();
    $allStatsRaw = $statsTracker->getAllDomainsStats($days);
    $statsCount = count($allStatsRaw);

    // 统计页分页
    $statsPagination = getPaginationData($statsCount, $perPage, $currentPage);
    $statsTotalPages = $statsPagination['total_pages'];
    $allStats = array_slice($allStatsRaw, $statsPagination['offset'], $perPage);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>域名管理后台</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>

<body data-admin-endpoint="<?php echo htmlspecialchars($adminAjaxEndpoint, ENT_QUOTES, 'UTF-8'); ?>" data-csrf-token="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- 顶部导航栏 -->
    <div class="topbar">
        <div class="topbar-inner admin-container">
            <div class="brand">
                <svg class="header-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor">
                    <path d="M797.866667 768H226.133333c-10.666667 0-17.066667 12.8-8.533333 21.333333 74.666667 78.933333 179.2 128 294.4 128s221.866667-49.066667 294.4-128c8.533333-8.533333 2.133333-21.333333-8.533333-21.333333zM192 298.666667h640c10.666667 0 17.066667-12.8 10.666667-21.333334-72.533333-102.4-194.133333-170.666667-330.666667-170.666666S253.866667 174.933333 181.333333 277.333333c-6.4 8.533333 0 21.333333 10.666667 21.333334z m640 64H192c-46.933333 0-85.333333 38.4-85.333333 85.333333v170.666667c0 46.933333 38.4 85.333333 85.333333 85.333333h640c46.933333 0 85.333333-38.4 85.333333-85.333333v-170.666667c0-46.933333-38.4-85.333333-85.333333-85.333333z m-437.333333 115.2L362.666667 618.666667c-4.266667 12.8-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666l-29.866667-98.133334-29.866667 98.133334c-4.266667 10.666667-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666l-32-140.8V469.333333c2.133333-10.666667 6.4-17.066667 17.066667-17.066666s19.2 6.4 21.333333 19.2l21.333334 108.8 32-110.933334c4.266667-8.533333 10.666667-14.933333 19.2-14.933333 10.666667 0 17.066667 4.266667 19.2 14.933333l32 110.933334 21.333333-108.8c2.133333-12.8 8.533333-19.2 19.2-19.2s17.066667 6.4 17.066667 17.066666c6.4 2.133333 6.4 6.4 6.4 8.533334z m230.4 0L593.066667 618.666667c-4.266667 12.8-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666L512 520.533333 482.133333 618.666667c-4.266667 10.666667-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666l-32-140.8V469.333333c2.133333-10.666667 6.4-17.066667 17.066667-17.066666s19.2 6.4 21.333333 19.2l21.333334 108.8L490.666667 469.333333c4.266667-8.533333 10.666667-14.933333 19.2-14.933333 10.666667 0 17.066667 4.266667 19.2 14.933333l32 110.933334 21.333333-108.8c2.133333-12.8 8.533333-19.2 19.2-19.2s17.066667 6.4 17.066667 17.066666c8.533333 2.133333 8.533333 6.4 6.4 8.533334z m232.533333 0L825.6 618.666667c-4.266667 12.8-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666l-29.866667-98.133334-29.866666 98.133334c-4.266667 10.666667-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666l-32-140.8V469.333333c2.133333-10.666667 6.4-17.066667 17.066666-17.066666s19.2 6.4 21.333334 19.2l21.333333 108.8 32-110.933334c4.266667-8.533333 10.666667-14.933333 19.2-14.933333 10.666667 0 17.066667 4.266667 19.2 14.933333l32 110.933334 21.333333-108.8c2.133333-12.8 8.533333-19.2 19.2-19.2s17.066667 6.4 17.066667 17.066666c8.533333 2.133333 6.4 6.4 6.4 8.533334z"></path>
                </svg>
                <span class="title">域名管理后台</span>
            </div>
            <div class="topbar-actions">
                <a href="<?php echo htmlspecialchars(adminLogoutUrl(), ENT_QUOTES, 'UTF-8'); ?>" class="logout-btn">
                    <div class="logout-sign">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="17" height="17">
                            <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z" />
                        </svg>
                    </div>
                    <span class="logout-text">退出登录</span>
                </a>
            </div>
        </div>
    </div>

    <div class="admin-layout admin-container">
        <!-- 侧边菜单 -->
        <aside class="sidebar">
            <nav class="menu">
                <a class="menu-item <?php echo $section === 'domains' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(adminSectionUrl('domains'), ENT_QUOTES, 'UTF-8'); ?>">域名管理</a>
                <a class="menu-item <?php echo $section === 'stats' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(adminSectionUrl('stats'), ENT_QUOTES, 'UTF-8'); ?>">访问统计</a>
                <a class="menu-item <?php echo $section === 'email' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(adminSectionUrl('email'), ENT_QUOTES, 'UTF-8'); ?>">邮件设置</a>
                <a class="menu-item <?php echo $section === 'site' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(adminSectionUrl('site'), ENT_QUOTES, 'UTF-8'); ?>">站点设置</a>
                <a class="menu-item <?php echo $section === 'footer' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(adminSectionUrl('footer'), ENT_QUOTES, 'UTF-8'); ?>">页脚设置</a>
            </nav>
        </aside>

        <!-- 主内容区域 -->
        <main class="content">
            <?php if ($section === 'domains'): ?>
                <div class="admin-header">
                    <h1>
                        <svg class="header-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor">
                            <path d="M797.866667 768H226.133333c-10.666667 0-17.066667 12.8-8.533333 21.333333 74.666667 78.933333 179.2 128 294.4 128s221.866667-49.066667 294.4-128c8.533333-8.533333 2.133333-21.333333-8.533333-21.333333zM192 298.666667h640c10.666667 0 17.066667-12.8 10.666667-21.333334-72.533333-102.4-194.133333-170.666667-330.666667-170.666666S253.866667 174.933333 181.333333 277.333333c-6.4 8.533333 0 21.333333 10.666667 21.333334z m640 64H192c-46.933333 0-85.333333 38.4-85.333333 85.333333v170.666667c0 46.933333 38.4 85.333333 85.333333 85.333333h640c46.933333 0 85.333333-38.4 85.333333-85.333333v-170.666667c0-46.933333-38.4-85.333333-85.333333-85.333333z m-437.333333 115.2L362.666667 618.666667c-4.266667 12.8-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666l-29.866667-98.133334-29.866667 98.133334c-4.266667 10.666667-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666l-32-140.8V469.333333c2.133333-10.666667 6.4-17.066667 17.066667-17.066666s19.2 6.4 21.333333 19.2l21.333334 108.8 32-110.933334c4.266667-8.533333 10.666667-14.933333 19.2-14.933333 10.666667 0 17.066667 4.266667 19.2 14.933333l32 110.933334 21.333333-108.8c2.133333-12.8 8.533333-19.2 19.2-19.2s17.066667 6.4 17.066667 17.066666c6.4 2.133333 6.4 6.4 6.4 8.533334z m230.4 0L593.066667 618.666667c-4.266667 12.8-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666L512 520.533333 482.133333 618.666667c-4.266667 10.666667-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666l-32-140.8V469.333333c2.133333-10.666667 6.4-17.066667 17.066667-17.066666s19.2 6.4 21.333333 19.2l21.333334 108.8L490.666667 469.333333c4.266667-8.533333 10.666667-14.933333 19.2-14.933333 10.666667 0 17.066667 4.266667 19.2 14.933333l32 110.933334 21.333333-108.8c2.133333-12.8 8.533333-19.2 19.2-19.2s17.066667 6.4 17.066667 17.066666c8.533333 2.133333 8.533333 6.4 6.4 8.533334z m232.533333 0L825.6 618.666667c-4.266667 12.8-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666l-29.866667-98.133334-29.866666 98.133334c-4.266667 10.666667-12.8 17.066667-25.6 17.066666s-21.333333-6.4-25.6-17.066666l-32-140.8V469.333333c2.133333-10.666667 6.4-17.066667 17.066666-17.066666s19.2 6.4 21.333334 19.2l21.333333 108.8 32-110.933334c4.266667-8.533333 10.666667-14.933333 19.2-14.933333 10.666667 0 17.066667 4.266667 19.2 14.933333l32 110.933334 21.333333-108.8c2.133333-12.8 8.533333-19.2 19.2-19.2s17.066667 6.4 17.066667 17.066666c8.533333 2.133333 6.4 6.4 6.4 8.533334z" />
                        </svg>
                        域名管理
                    </h1>
                    <span class="domain-count" id="domainCountDisplay">共 <?php echo $domainsCount; ?> 个域名</span>
                    <div class="domain-search-wrapper">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <input type="text" id="domainSearch" class="domain-search-input" placeholder="搜索域名..." autocomplete="off">
                    </div>
                    <div class="header-actions">
                        <div class="pagination-controls">
                            <label for="perPageSelect" style="font-size: 13px; color: var(--text-secondary); margin-right: 8px;">每页:</label>
                            <select id="perPageSelect" class="per-page-select" onchange="changePerPage(this.value)">
                                <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo $perPage == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="30" <?php echo $perPage == 30 ? 'selected' : ''; ?>>30</option>
                                <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                            </select>
                        </div>
                        <button class="btn" onclick="showAddModal()">添加</button>
                    </div>
                </div>
                <div class="domains-list" id="domainsList">
                    <?php foreach ($domains as $domain): ?>
                        <div class="domain-row" data-id="<?php echo $domain['id']; ?>" data-domain="<?php echo htmlspecialchars(strtolower($domain['domain'])); ?>" data-title="<?php echo htmlspecialchars(strtolower($domain['title'])); ?>">
                            <div class="domain-row-main">
                                <div class="domain-row-name">
                                    <span class="domain-name-text">
                                        <?php
                                        $domainName = htmlspecialchars($domain['domain']);
                                        $parts = explode('.', $domainName, 2);
                                        if (count($parts) == 2) {
                                            echo '<span class="domain-name-part">' . $parts[0] . '</span>';
                                            echo '<span class="domain-name-dot">.</span>';
                                            echo '<span class="domain-name-part">' . $parts[1] . '</span>';
                                        } else {
                                            echo '<span class="domain-name-part">' . $domainName . '</span>';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="domain-row-info">
                                    <span class="row-info-item row-info-title"><?php echo htmlspecialchars($domain['title']); ?></span>
                                    <span class="row-info-item row-info-color">
                                        <span class="color-preview" style="background-color: <?php echo $domain['theme_color']; ?>"></span>
                                        <?php echo $domain['theme_color']; ?>
                                    </span>
                                    <?php if ($domain['domain_intro']): ?>
                                        <span class="row-info-item row-info-intro" title="<?php echo htmlspecialchars($domain['domain_intro']); ?>">
                                            <?php echo mb_strlen($domain['domain_intro']) > 30 ? mb_substr($domain['domain_intro'], 0, 30) . '...' : htmlspecialchars($domain['domain_intro']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($domain['domain_price']): ?>
                                        <span class="row-info-item row-info-price">¥<?php echo htmlspecialchars($domain['domain_price']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="domain-row-actions">
                                    <button class="btn-icon btn-whois-toggle" type="button" data-domain="<?php echo htmlspecialchars($domain['domain'], ENT_QUOTES, 'UTF-8'); ?>" title="WHOIS 一键查询" aria-expanded="false">
                                        <svg class="whois-icon" viewBox="0 0 1024 1024" aria-hidden="true" focusable="false">
                                            <path d="M707.621926 350.549333l-45.037037 6.637037C636.416 179.465481 567.580444 60.681481 498.839704 60.681481c-68.077037 0-136.343704 116.508444-163.081482 291.802075l-44.980148-6.864593C320.587852 150.243556 399.701333 15.17037 498.839704 15.17037c99.972741 0 179.617185 137.462519 208.782222 335.378963zM290.664296 677.641481l44.999111-6.826666c26.661926 175.653926 95.004444 292.503704 163.176297 292.503704 68.266667 0 136.722963-117.229037 163.271111-293.281186l44.999111 6.788741C677.546667 872.997926 598.224593 1008.82963 498.839704 1008.82963c-99.252148 0-178.460444-135.433481-208.175408-331.188149z" fill="currentColor"></path>
                                            <path d="M512 1008.82963C237.605926 1008.82963 15.17037 786.394074 15.17037 512 15.17037 237.605926 237.605926 15.17037 512 15.17037 786.394074 15.17037 1008.82963 237.605926 1008.82963 512c0 274.394074-222.435556 496.82963-496.82963 496.82963z m0-45.511111c249.249185 0 451.318519-202.069333 451.318519-451.318519S761.249185 60.681481 512 60.681481 60.681481 262.750815 60.681481 512 262.750815 963.318519 512 963.318519z" fill="currentColor"></path>
                                            <path d="M64.265481 376.737185v-45.511111H959.715556v45.511111H64.284444zM959.715556 647.262815v45.511111H64.284444v-45.511111H959.715556z" fill="currentColor"></path>
                                            <path d="M118.139259 429.131852h31.288889l31.744 128.720592h0.948148l33.431704-128.701629h28.672l33.431704 128.701629h0.948148l31.762963-128.701629h31.288889l-48.82963 169.244444h-29.392593l-32.957629-127.29837h-0.948148l-33.185185 127.29837H166.72237L118.120296 429.131852z m241.284741 0h27.742815v70.656h85.807407V429.131852h27.723852v169.244444h-27.723852v-74.903703h-85.807407v74.903703h-27.742815V429.131852z m249.609481-3.299556c25.903407 0 46.288593 8.362667 61.155556 25.125926 14.222222 15.796148 21.333333 36.807111 21.333333 63.051852 0 25.903407-7.111111 46.838519-21.333333 62.805333-14.866963 16.592593-35.252148 24.89837-61.155556 24.898371-25.92237 0-46.307556-8.38163-61.155555-25.125926-14.070519-15.966815-21.105778-36.826074-21.105778-62.577778 0-25.92237 7.035259-46.857481 21.105778-62.824296 14.52563-16.914963 34.910815-25.353481 61.155555-25.353482z m0 24.405334c-17.389037 0-30.985481 5.935407-40.77037 17.787259-9.178074 11.207111-13.748148 26.548148-13.748148 45.985185 0 19.26637 4.570074 34.512593 13.748148 45.738667 9.481481 11.700148 23.058963 17.540741 40.77037 17.54074 17.540741 0 31.04237-5.613037 40.523852-16.820148 9.329778-11.226074 13.994667-26.718815 13.994667-46.459259 0-19.759407-4.664889-35.403852-13.994667-46.933333-9.329778-11.226074-22.831407-16.839111-40.523852-16.839111z m108.562963-21.086815h27.723852v169.244444h-27.723852V429.131852z m120.642371-3.318519c20.081778 0 35.65037 4.096 46.705778 12.325926 11.851852 8.836741 18.640593 22.509037 20.385185 40.997926h-27.496297c-2.37037-10.42963-6.712889-17.938963-13.046518-22.509037-6.011259-4.589037-15.322074-6.883556-27.97037-6.883555-10.752 0-18.887111 1.517037-24.405334 4.513185-6.959407 3.470222-10.42963 9.310815-10.429629 17.54074 0 7.414519 3.944296 13.179259 11.851851 17.294223 3.792593 2.048 13.425778 5.537185 28.918519 10.429629 22.281481 6.788741 37.05363 12.325926 44.316444 16.592593 14.696296 8.685037 22.053926 20.859259 22.053926 36.503704 0 15.17037-5.935407 27.173926-17.787259 36.029629-12.003556 8.685037-28.747852 13.046519-50.251852 13.046519-20.859259 0-37.129481-4.039111-48.829629-12.098371-14.373926-9.955556-22.110815-25.675852-23.22963-47.160888h27.496296c1.896296 12.951704 6.485333 22.110815 13.748148 27.496296 6.807704 4.892444 17.066667 7.338667 30.814815 7.338666 12.325926 0 22.129778-2.048 29.392593-6.162962 7.281778-4.41837 10.903704-10.05037 10.903704-16.820149 0-9.007407-5.290667-16.118519-15.872-21.333333-3.792593-1.896296-14.791111-5.613037-32.95763-11.150222-21.010963-6.637037-33.962667-11.377778-38.874074-14.222222-12.951704-7.736889-19.437037-19.114667-19.437037-34.133334 0-15.17037 6.314667-26.927407 18.962963-35.309037 11.700148-8.229926 26.699852-12.325926 45.037037-12.325926z" fill="currentColor"></path>
                                        </svg>
                                        <span class="whois-btn-spinner" aria-hidden="true"></span>
                                    </button>
                                    <button class="btn-icon" onclick="editDomain(<?php echo $domain['id']; ?>)" title="编辑">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke-linejoin="round" stroke-linecap="round"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke-linejoin="round" stroke-linecap="round"></path>
                                        </svg>
                                    </button>
                                    <button class="btn-icon btn-delete" onclick="deleteDomain(<?php echo $domain['id']; ?>, '<?php echo htmlspecialchars($domain['domain'], ENT_QUOTES); ?>')" title="删除">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                                            <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke-linejoin="round" stroke-linecap="round"></path>
                                            <line x1="10" y1="11" x2="10" y2="17" stroke-linejoin="round" stroke-linecap="round"></line>
                                            <line x1="14" y1="11" x2="14" y2="17" stroke-linejoin="round" stroke-linecap="round"></line>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($domains)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="64" height="64">
                                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
                                </svg>
                            </div>
                            <p>还没有添加任何域名</p>
                            <button class="btn btn-primary" onclick="showAddModal()">添加第一个域名</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($domainsCount > 0): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination">
                                <?php if ($currentPage > 1): ?>
                                    <a href="<?php echo htmlspecialchars(adminSectionUrl('domains', ['page' => $currentPage - 1, 'per_page' => $perPage]), ENT_QUOTES, 'UTF-8'); ?>" class="pagination-btn">上一页</a>
                                <?php else: ?>
                                    <span class="pagination-btn disabled">上一页</span>
                                <?php endif; ?>

                                <span class="pagination-info">第 <?php echo $currentPage; ?> / <?php echo $totalPages; ?> 页</span>

                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="<?php echo htmlspecialchars(adminSectionUrl('domains', ['page' => $currentPage + 1, 'per_page' => $perPage]), ENT_QUOTES, 'UTF-8'); ?>" class="pagination-btn">下一页</a>
                                <?php else: ?>
                                    <span class="pagination-btn disabled">下一页</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'stats'): ?>
                <div class="admin-header">
                    <h1>
                        <svg class="stats-icon header-icon" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z" />
                        </svg>
                        访问统计
                    </h1>
                    <div class="domain-search-wrapper">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <input type="text" id="statsDomainSearch" class="domain-search-input" placeholder="搜索域名..." autocomplete="off">
                    </div>
                    <div class="header-actions">
                        <div class="pagination-controls">
                            <label for="statsPerPageSelect" style="font-size: 13px; color: var(--text-secondary); margin-right: 8px;">每页:</label>
                            <select id="statsPerPageSelect" class="per-page-select" onchange="changeStatsPerPage(this.value)">
                                <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo $perPage == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="30" <?php echo $perPage == 30 ? 'selected' : ''; ?>>30</option>
                                <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                            </select>
                        </div>
                        <form method="get" class="stats-filter">
                            <input type="hidden" name="section" value="stats">
                            <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                            <input type="hidden" name="page" value="1">
                            <select name="days" onchange="this.form.submit()">
                                <option value="7" <?php echo $days == 7 ? 'selected' : ''; ?>>最近7天</option>
                                <option value="30" <?php echo $days == 30 ? 'selected' : ''; ?>>最近30天</option>
                                <option value="90" <?php echo $days == 90 ? 'selected' : ''; ?>>最近90天</option>
                                <option value="365" <?php echo $days == 365 ? 'selected' : ''; ?>>最近1年</option>
                            </select>
                        </form>
                    </div>
                </div>
                <?php if (empty($allStats)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor" width="64" height="64">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
                            </svg>
                        </div>
                        <p>暂无访问记录</p>
                    </div>
                <?php else: ?>
                    <div class="stats-list" id="statsList">
                        <?php foreach ($allStats as $stat): ?>
                            <div class="stats-row" data-domain="<?php echo htmlspecialchars(strtolower($stat['domain'])); ?>">
                                <div class="stats-row-main">
                                    <div class="stats-row-domain">
                                        <svg class="domain-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="18" height="18">
                                            <path d="M512 64C264.6 64 64 264.6 64 512s200.6 448 448 448 448-200.6 448-448S759.4 64 512 64z m193.5 301.7l-210.6 292a31.8 31.8 0 0 1-51.7 0L318.5 365.7c-3.8-5.3 0-12.7 6.5-12.7h46.9c10.3 0 19.9 5 25.9 13.3l71.2 98.8 157.2-218c6-8.4 15.7-13.3 25.9-13.3H699c6.5 0 10.3 7.4 6.5 12.7z" fill="#2867CE"></path>
                                        </svg>
                                        <span class="stats-domain-text"><?php echo htmlspecialchars($stat['domain']); ?></span>
                                    </div>
                                    <div class="stats-row-data">
                                        <span class="stats-data-item">
                                            <span class="stats-data-label">访问量:</span>
                                            <span class="stats-data-value"><?php echo number_format($stat['total_visits']); ?></span>
                                        </span>
                                        <span class="stats-data-item">
                                            <span class="stats-data-label">访客:</span>
                                            <span class="stats-data-value"><?php echo number_format($stat['unique_ips']); ?></span>
                                        </span>
                                        <span class="stats-data-item">
                                            <span class="stats-data-label">最后访问:</span>
                                            <span class="stats-data-value"><?php echo htmlspecialchars($stat['last_visit']); ?></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($allStatsRaw ?? []) > 0): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination">
                                <?php if ($currentPage > 1): ?>
                                    <a href="<?php echo htmlspecialchars(adminSectionUrl('stats', ['page' => $currentPage - 1, 'per_page' => $perPage, 'days' => $days]), ENT_QUOTES, 'UTF-8'); ?>" class="pagination-btn">上一页</a>
                                <?php else: ?>
                                    <span class="pagination-btn disabled">上一页</span>
                                <?php endif; ?>

                                <span class="pagination-info">第 <?php echo $currentPage; ?> / <?php echo $statsTotalPages; ?> 页</span>

                                <?php if ($currentPage < $statsTotalPages): ?>
                                    <a href="<?php echo htmlspecialchars(adminSectionUrl('stats', ['page' => $currentPage + 1, 'per_page' => $perPage, 'days' => $days]), ENT_QUOTES, 'UTF-8'); ?>" class="pagination-btn">下一页</a>
                                <?php else: ?>
                                    <span class="pagination-btn disabled">下一页</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php elseif ($section === 'email'): ?>
                <div class="admin-header">
                    <h1>
                        <svg class="header-icon" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
                        </svg>
                        邮件设置
                    </h1>
                </div>
                <div class="card">
                    <form id="emailSettingsForm" class="email-settings-form" onsubmit="saveEmailSettings(event)">
                        <section class="email-settings-section">
                            <div class="email-settings-section-header">
                                <h3>收件人设置</h3>
                                <p>测试邮件默认会使用这个邮箱，可按需手动修改。</p>
                            </div>
                            <div class="form-group">
                                <label for="default_to_email">默认收件人邮箱</label>
                                <input type="email" id="default_to_email" name="default_to_email" placeholder="admin@example.com">
                            </div>
                        </section>

                        <section class="email-settings-section">
                            <div class="email-settings-section-header">
                                <h3>发件人与 SMTP</h3>
                                <p>用于系统发送询价通知、测试邮件和自动通知。</p>
                            </div>
                            <div class="form-row form-row-two">
                                <div class="form-group">
                                    <label for="from_name">默认发件人名称</label>
                                    <input type="text" id="from_name" name="from_name" placeholder="例如：域名停放系统">
                                </div>
                                <div class="form-group">
                                    <label for="from_email">默认发件人邮箱</label>
                                    <input type="email" id="from_email" name="from_email" placeholder="noreply@example.com">
                                </div>
                            </div>
                            <div class="form-row form-row-three">
                                <div class="form-group">
                                    <label for="smtp_encryption">加密方式</label>
                                    <select id="smtp_encryption" name="smtp_encryption">
                                        <option value="none">不加密</option>
                                        <option value="tls">STARTTLS</option>
                                        <option value="ssl">SSL/TLS</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="smtp_host">SMTP 服务器</label>
                                    <input type="text" id="smtp_host" name="smtp_host" placeholder="smtp.example.com">
                                </div>
                                <div class="form-group">
                                    <label for="smtp_port">端口</label>
                                    <input type="number" id="smtp_port" name="smtp_port" placeholder="587">
                                </div>
                            </div>
                            <div class="form-row form-row-two">
                                <div class="form-group">
                                    <label for="smtp_username">用户名</label>
                                    <input type="text" id="smtp_username" name="smtp_username" placeholder="user@example.com">
                                </div>
                                <div class="form-group">
                                    <label for="smtp_password">密码</label>
                                    <input type="password" id="smtp_password" name="smtp_password" placeholder="••••••••">
                                </div>
                            </div>
                        </section>

                        <section class="email-settings-section">
                            <div class="email-settings-section-header">
                                <h3>测试邮件</h3>
                                <p>发送前可临时修改收件邮箱，不会覆盖默认配置。</p>
                            </div>
                            <div class="form-group">
                                <label for="test_email_to">测试邮件收件邮箱</label>
                                <input type="email" id="test_email_to" name="test_email_to" placeholder="test@example.com">
                            </div>
                        </section>

                        <div class="email-settings-actions">
                            <button type="button" class="btn btn-secondary" id="testEmailBtn" onclick="sendTestEmail()">发送测试邮件</button>
                            <button type="submit" class="btn btn-primary">保存设置</button>
                        </div>
                    </form>
                </div>
                <div class="card">
                    <h2>说明</h2>
                    <p>系统使用 SMTP 服务器进行邮件发送。请正确配置 SMTP 服务器信息以确保邮件正常发送。默认收件人邮箱用于接收客户的购买咨询邮件。</p>
                </div>
            <?php elseif ($section === 'site'): ?>
                <div class="admin-header">
                    <h1>
                        <svg class="header-icon" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                        </svg>
                        站点设置
                    </h1>
                </div>
                <div class="card">
                    <form id="siteSettingsForm" onsubmit="saveSiteSettings(event)">
                        <div class="form-group">
                            <label for="old_password">原密码</label>
                            <input type="password" id="old_password" name="old_password" placeholder="修改密码时需要输入原密码">
                            <small style="color: var(--text-secondary); font-size: 13px; margin-top: 4px; display: block;">仅修改密码时需要填写</small>
                        </div>
                        <div class="form-group">
                            <label for="admin_password">新密码</label>
                            <input type="password" id="admin_password" name="admin_password" placeholder="留空则不修改密码，至少8位">
                            <small style="color: var(--text-secondary); font-size: 13px; margin-top: 4px; display: block;">留空则不修改当前密码</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">确认新密码</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="再次输入新密码">
                        </div>
                        <hr style="border:none;border-top:1px solid var(--border-color);margin:20px 0;">
                        <div class="form-group">
                            <label for="site_name">站点名称</label>
                            <input type="text" id="site_name" name="site_name" placeholder="例如：域名管理后台">
                        </div>
                        <div class="modal-footer" style="justify-content:flex-end; gap:12px;">
                            <button type="submit" class="btn btn-primary">保存设置</button>
                        </div>
                    </form>
                </div>
                <div class="card">
                    <h2>说明</h2>
                    <p>修改密码时需要输入原密码进行验证。站点名称可用于系统标识，可根据需要进行配置。</p>
                </div>
            <?php elseif ($section === 'footer'): ?>
                <div class="admin-header">
                    <h1>
                        <svg class="header-icon" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                            <path d="M3 5.75A2.75 2.75 0 0 1 5.75 3h12.5A2.75 2.75 0 0 1 21 5.75v12.5A2.75 2.75 0 0 1 18.25 21H5.75A2.75 2.75 0 0 1 3 18.25V5.75zm4.25 2a.75.75 0 0 0 0 1.5h9.5a.75.75 0 0 0 0-1.5h-9.5zm0 3.5a.75.75 0 0 0 0 1.5h5.5a.75.75 0 0 0 0-1.5h-5.5zm0 3.5a.75.75 0 0 0 0 1.5h8a.75.75 0 0 0 0-1.5h-8z" />
                        </svg>
                        页脚设置
                    </h1>
                </div>
                <div class="card">
                    <form id="footerSettingsForm" onsubmit="saveFooterSettings(event)">
                        <div class="form-group">
                            <label>页脚链接</label>
                            <small style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 10px;">
                                图标支持 Font Awesome 类名（如 <code>fa-solid fa-globe</code>）或 SVG 代码（如 <code>&lt;svg&gt;...&lt;/svg&gt;</code>），系统会自动识别。URL 支持 <code>{domain}</code> 占位符。最多 3 个链接，GitHub 为固定版权链接不可修改。
                            </small>
                            <div id="footerLinksContainer" class="footer-links-editor"></div>
                            <button type="button" class="btn btn-secondary" onclick="addFooterLinkRow()" style="margin-top: 12px;">添加链接</button>
                        </div>

                        <hr style="border:none;border-top:1px solid var(--border-color);margin:20px 0;">

                        <div class="form-group">
                            <label for="footer_analytics_code">统计代码</label>
                            <textarea id="footer_analytics_code" name="footer_analytics_code" rows="6" placeholder="粘贴第三方统计代码，例如 Google Analytics / 百度统计代码"></textarea>
                            <small style="color: var(--text-secondary); font-size: 13px; margin-top: 4px; display: block;">会插入到前台页面底部。</small>
                        </div>

                        <div class="modal-footer" style="justify-content:flex-end; gap:12px;">
                            <button type="submit" class="btn btn-primary">保存设置</button>
                        </div>
                    </form>
                </div>
                <div class="card">
                    <h2>说明</h2>
                    <p>页脚链接会按填写顺序显示。图标支持两种格式：Font Awesome 类名（如 <code>fa-solid fa-globe</code>、<code>fa-brands fa-github</code>）或直接粘贴 SVG 代码。系统会自动识别格式并渲染对应图标。</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- 添加/编辑域名模态框 -->
    <div id="domainModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">添加域名</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="domainForm" onsubmit="saveDomain(event)">
                <input type="hidden" id="domainId" name="id">

                <div class="form-group">
                    <label for="domain">域名 *</label>
                    <input type="text" id="domain" name="domain" required placeholder="example.com">
                </div>

                <div class="form-group">
                    <label for="title">标题 *</label>
                    <input type="text" id="title" name="title" required placeholder="网站标题">
                </div>

                <div class="form-group">
                    <label for="theme_color">主题颜色</label>
                    <input type="color" id="theme_color" name="theme_color" value="#0065F3">
                </div>

                <div class="form-group">
                    <label for="domain_intro">域名介绍</label>
                    <textarea id="domain_intro" name="domain_intro" rows="4" placeholder="请输入域名的详细介绍，如域名的特点、用途、优势等"></textarea>
                </div>

                <div class="form-group">
                    <label for="domain_price">域名价格</label>
                    <div class="price-input-wrapper-admin">
                        <span class="currency-symbol-admin">¥</span>
                        <input type="text" id="domain_price" name="domain_price" placeholder="例如：10,000 或 面议">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 确认删除模态框 -->
    <div id="confirmDeleteModal" class="modal">
        <div class="modal-content confirm-delete-modal">
            <div class="modal-header">
                <div class="confirm-delete-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </div>
                <h2>确认删除</h2>
                <button class="modal-close" onclick="document.getElementById('confirmDeleteModal').classList.remove('show')">&times;</button>
            </div>
            <div class="confirm-delete-content">
                <p id="confirmDeleteText" class="confirm-delete-text"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">取消</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="margin-right: 6px;">
                        <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                    确认删除
                </button>
            </div>
        </div>
    </div>

    <!-- WHOIS 查询模态框 -->
    <div id="whoisModal" class="modal" aria-hidden="true">
        <div class="modal-content whois-modal-content">
            <div class="modal-header">
                <h2 id="whoisModalTitle">WHOIS 查询</h2>
                <button class="modal-close" type="button" onclick="closeWhoisModal()">&times;</button>
            </div>
            <div class="whois-modal-body">
                <div class="whois-modal-toolbar">
                    <button type="button" class="btn btn-secondary" id="whoisCopyBtn" disabled title="复制结果" aria-label="复制结果">
                        ⧉
                    </button>
                </div>
                <div id="whoisModalBody" class="whois-content"></div>
            </div>
        </div>
    </div>

    <footer class="admin-footer">
        <div class="admin-footer-content">
            <p class="admin-footer-copyright">
                &copy; <?php echo date('Y'); ?> 域名管理系统
            </p>
            <p class="admin-footer-author">
                程序作者：<a href="https://xifeng.net" target="_blank" rel="noopener noreferrer">西风</a>
            </p>
            <p class="admin-footer-links">
                <a href="https://github.com/gentpan/namedeal" target="_blank" rel="noopener noreferrer">
                    <svg class="github-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                        <path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.463-1.11-1.463-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"></path>
                    </svg>
                    GitHub
                </a>
            </p>
            <div class="theme-toggle-wrapper">
                <div class="theme-toggle" title="主题" id="adminThemeToggle">
                    <svg class="current-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                        <path d="M20 3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h4v2H8v2h8v-2h-4v-2h4c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 12H4V5h16v10z" />
                    </svg>
                    <span class="theme-toggle-text">主题</span>
                </div>
                <div class="theme-menu" id="adminThemeMenu">
                    <div class="theme-menu-item" data-theme-mode="light">
                        <svg class="theme-menu-icon" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                            <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z" />
                        </svg>
                        <span class="theme-menu-text">浅色模式</span>
                        <svg class="theme-menu-check" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display: none;">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                        </svg>
                    </div>
                    <div class="theme-menu-separator"></div>
                    <div class="theme-menu-item" data-theme-mode="dark">
                        <svg class="theme-menu-icon" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                            <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd" />
                        </svg>
                        <span class="theme-menu-text">深色模式</span>
                        <svg class="theme-menu-check" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display: none;">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                        </svg>
                    </div>
                    <div class="theme-menu-separator"></div>
                    <div class="theme-menu-item" data-theme-mode="auto">
                        <svg class="theme-menu-icon" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                            <path d="M20 3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h4v2H8v2h8v-2h-4v-2h4c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 12H4V5h16v10z" />
                        </svg>
                        <span class="theme-menu-text">跟随系统</span>
                        <svg class="theme-menu-check" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display: none;">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="/assets/js/admin.js"></script>
</body>

</html>
