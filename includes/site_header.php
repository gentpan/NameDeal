<?php
$siteTitle = $siteSettings['site_title'] ?? 'DOMAIN.LS';
$siteDescription = $siteSettings['site_description'] ?? '';
$logoSubtitle = $siteSettings['logo_subtitle'] ?? 'PREMIUM DOMAIN COLLECTION';
$siteName = $siteSettings['site_name'] ?? '://domain.ls';
$isHomePage = isset($view) && $view === 'home';
$pageTitle = $siteTitle !== '' ? $siteTitle : 'domain.ls - 域名列表';
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo h($siteDescription); ?>">
    <title><?php echo h($pageTitle); ?></title>
    <link rel="stylesheet" href="https://icons.bluecdn.com/fontawesome-pro/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="page-loading">
    <div class="initial-page-loader" id="initialPageLoader" aria-live="polite" aria-label="页面加载中">
        <div class="initial-page-loader-modal">
            <div class="initial-page-loader-spinner"></div>
        </div>
    </div>
    <div class="page-corner-loader" id="pageCornerLoader" aria-hidden="true">
        <div class="page-corner-loader-spinner"></div>
    </div>
    <script>
        (function () {
            var body = document.body;
            var isHomePage = <?php echo $isHomePage ? 'true' : 'false'; ?>;
            var useFullscreen = false;

            try {
                var hasVisitedHome = sessionStorage.getItem('domainls_home_visited') === '1';
                var navEntries = (performance && performance.getEntriesByType) ? performance.getEntriesByType('navigation') : [];
                var navType = (navEntries && navEntries[0] && navEntries[0].type) ? navEntries[0].type : '';
                var isHomeReload = isHomePage && navType === 'reload';

                useFullscreen = isHomePage && (!hasVisitedHome || isHomeReload);

                if (isHomePage) {
                    sessionStorage.setItem('domainls_home_visited', '1');
                }
            } catch (e) {
                useFullscreen = isHomePage;
            }

            body.classList.add(useFullscreen ? 'page-loading-mode-fullscreen' : 'page-loading-mode-corner');
        })();
    </script>
    <div class="top-bar">
        <div class="top-bar-container">
            <a class="top-brand" href="/" aria-label="DOMAIN.LS 首页">
                <span class="top-brand-text">
                    <span class="top-brand-title"><?php echo h($siteName); ?></span>
                </span>
            </a>
            <nav class="top-nav">
                <a href="/">首页</a>
                <a href="/#domains">域名</a>
                <a href="/whois">whois</a>
                <a href="/contact">联系</a>
            </nav>
            <div class="top-subtitle-right"><?php echo h($logoSubtitle); ?></div>
        </div>
    </div>
