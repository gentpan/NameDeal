<?php
require_once __DIR__ . '/../app/helpers.php';
$content = read_json('content.json', []);
$siteSettings = $content['site_settings'] ?? [];

require __DIR__ . '/../includes/site_header.php';

$pageContentClass = $page_content_class ?? 'page-content';
$pageInnerClass = $page_inner_class ?? 'page-content-inner';
?>
    <section class="page-hero">
        <div class="section-container">
            <h1 class="page-title"><?php echo h($page_title ?? '页面'); ?></h1>
            <p class="page-subtitle"><?php echo h($page_subtitle ?? ''); ?></p>
        </div>
    </section>

    <section class="<?php echo h($pageContentClass); ?>">
        <div class="<?php echo h($pageInnerClass); ?>">
            <?php echo $page_content ?? ''; ?>
        </div>
    </section>

    <?php if (!empty($page_extra_script)): ?>
        <?php echo $page_extra_script; ?>
    <?php endif; ?>

<?php
require __DIR__ . '/../includes/site_footer.php';
