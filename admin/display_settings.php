<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
require_admin();
require __DIR__ . '/_layout.php';

$defaults = [
    'site_max_width' => 1600,
    'columns_per_row' => 4,
    'items_per_page' => 8,
    'card_style' => 'standard',
];

$config = array_merge($defaults, read_json('ui_settings.json', []));
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $siteMaxWidth = max(1200, min(2200, (int)($_POST['site_max_width'] ?? 1600)));
    $columnsPerRow = max(2, min(6, (int)($_POST['columns_per_row'] ?? 4)));
    $itemsPerPage = max($columnsPerRow, (int)($_POST['items_per_page'] ?? 8));

    if ($itemsPerPage % $columnsPerRow !== 0) {
        $itemsPerPage = (int)(ceil($itemsPerPage / $columnsPerRow) * $columnsPerRow);
        $flash = '每页数量已自动调整为每行列数的倍数：' . $itemsPerPage;
    }

    $cardStyle = (string)($_POST['card_style'] ?? 'standard');
    if (!in_array($cardStyle, ['standard', 'small', 'list'], true)) {
        $cardStyle = 'standard';
    }

    $config = [
        'site_max_width' => $siteMaxWidth,
        'columns_per_row' => $columnsPerRow,
        'items_per_page' => $itemsPerPage,
        'card_style' => $cardStyle,
    ];

    write_json('ui_settings.json', $config);

    if ($flash === '') {
        $flash = '展示设置已保存';
    }
}

admin_header('展示设置');
?>
<div class="admin-card">
    <?php if ($flash !== ''): ?>
        <div class="alert alert-success"><?php echo h($flash); ?></div>
    <?php endif; ?>

    <form method="post">
        <?php echo csrf_input(); ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">页面最大宽度 (px)</label>
                <input class="form-control" type="number" min="1200" max="2200" name="site_max_width" value="<?php echo h((string)$config['site_max_width']); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">每行域名卡片数</label>
                <input class="form-control" type="number" min="2" max="6" name="columns_per_row" value="<?php echo h((string)$config['columns_per_row']); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">每页显示域名数</label>
                <input class="form-control" type="number" min="2" max="60" name="items_per_page" value="<?php echo h((string)$config['items_per_page']); ?>">
                <div class="form-text">会自动调整为每行卡片数的倍数</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">卡片样式</label>
                <select class="form-select" name="card_style">
                    <option value="standard" <?php echo $config['card_style'] === 'standard' ? 'selected' : ''; ?>>大卡片</option>
                    <option value="small" <?php echo $config['card_style'] === 'small' ? 'selected' : ''; ?>>小卡片</option>
                    <option value="list" <?php echo $config['card_style'] === 'list' ? 'selected' : ''; ?>>列表形式（单行）</option>
                </select>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit">保存设置</button>
            <a class="btn btn-outline-secondary" href="/admin/domains.php">返回域名管理</a>
        </div>
    </form>
</div>
<?php
admin_footer();
