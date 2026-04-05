<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
require_admin();
require __DIR__ . '/_layout.php';

$suffixes = read_json('suffixes.json', []);
$error = '';
$flash = '';

if (($_GET['action'] ?? '') === 'export_json') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="suffixes-' . date('Ymd-His') . '.json"');
    echo json_encode($suffixes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['import']) && $_GET['import'] === 'ok') {
    $count = max(0, (int)($_GET['count'] ?? 0));
    $flash = '后缀 JSON 导入成功，共 ' . $count . ' 条。';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'import_json') {
        $file = $_FILES['json_file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = '请选择有效的 JSON 文件';
        } else {
            $raw = file_get_contents((string)$file['tmp_name']);
            $decoded = json_decode((string)$raw, true);
            if (isset($decoded['suffixes']) && is_array($decoded['suffixes'])) {
                $decoded = $decoded['suffixes'];
            }
            if (!is_array($decoded)) {
                $error = 'JSON 格式错误，需为数组';
            } else {
                $imported = [];
                $used = [];
                foreach ($decoded as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $tld = normalize_suffix((string)($row['tld'] ?? ''));
                    if ($tld === '' || !is_valid_suffix($tld) || isset($used[$tld])) {
                        continue;
                    }
                    $used[$tld] = true;
                    $imported[] = [
                        'tld' => $tld,
                        'label' => trim((string)($row['label'] ?? '')),
                    ];
                }
                if ($imported === []) {
                    $error = '导入失败：JSON 中没有有效后缀数据';
                } else {
                    usort($imported, static fn (array $a, array $b): int => strcmp((string)$a['tld'], (string)$b['tld']));
                    write_json('suffixes.json', $imported);
                    header('Location: /admin/suffixes.php?import=ok&count=' . count($imported));
                    exit;
                }
            }
        }
    } elseif (isset($_POST['delete_tld'])) {
        $deleteTld = normalize_suffix((string)$_POST['delete_tld']);
        $beforeCount = count($suffixes);
        $suffixes = array_values(array_filter($suffixes, function ($item) use ($deleteTld) {
            return normalize_suffix((string)($item['tld'] ?? '')) !== $deleteTld;
        }));
        if ($beforeCount === count($suffixes)) {
            $error = '要删除的后缀不存在';
        } else {
            write_json('suffixes.json', $suffixes);
            header('Location: /admin/suffixes.php');
            exit;
        }
    } else {
        $tld = normalize_suffix((string)($_POST['tld'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        if ($tld === '') {
            $error = '后缀不能为空';
        } elseif (!is_valid_suffix($tld)) {
            $error = '后缀格式不正确，例如 .com 或 .co.uk';
        } else {
            $exists = false;
            foreach ($suffixes as $item) {
                if (normalize_suffix((string)($item['tld'] ?? '')) === $tld) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                $error = '该后缀已存在';
            } else {
                $suffixes[] = [
                    'tld' => $tld,
                    'label' => $label,
                ];
                write_json('suffixes.json', $suffixes);
                header('Location: /admin/suffixes.php');
                exit;
            }
        }
    }
}

admin_header('后缀分类');
?>
<div class="admin-card">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($flash !== ''): ?>
        <div class="alert alert-success"><?php echo h($flash); ?></div>
    <?php endif; ?>
    <form class="row g-3 mb-4" method="post" enctype="multipart/form-data">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="import_json">
        <div class="col-md-6">
            <label class="form-label">导入后缀 JSON</label>
            <input class="form-control" type="file" name="json_file" accept=".json,application/json" required>
        </div>
        <div class="col-md-6 d-flex align-items-end gap-2">
            <button class="btn btn-outline-primary" type="submit">导入 JSON</button>
            <a class="btn btn-outline-secondary" href="/admin/suffixes.php?action=export_json">导出 JSON</a>
        </div>
    </form>
    <form class="row g-3 mb-4" method="post">
        <?php echo csrf_input(); ?>
        <div class="col-md-4">
            <label class="form-label">后缀</label>
            <input class="form-control" type="text" name="tld" placeholder="例如 .net">
        </div>
        <div class="col-md-6">
            <label class="form-label">说明</label>
            <input class="form-control" type="text" name="label" placeholder="例如 国际通用">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary" type="submit">添加</button>
        </div>
    </form>

    <table class="table table-hover admin-table">
        <thead>
            <tr>
                <th>后缀</th>
                <th>说明</th>
                <th class="text-end">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($suffixes as $suffix): ?>
                <tr>
                    <td><?php echo h($suffix['tld'] ?? ''); ?></td>
                    <td><?php echo h($suffix['label'] ?? ''); ?></td>
                    <td class="text-end">
                        <form method="post" class="d-inline" onsubmit="return confirm('确认删除该后缀吗？')">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="delete_tld" value="<?php echo h($suffix['tld'] ?? ''); ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">删除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
admin_footer();
