<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
require_admin();
require __DIR__ . '/_layout.php';

$categories = read_json('categories.json', []);
$error = '';
$flash = '';

if (($_GET['action'] ?? '') === 'export_json') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="categories-' . date('Ymd-His') . '.json"');
    echo json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['import']) && $_GET['import'] === 'ok') {
    $count = max(0, (int)($_GET['count'] ?? 0));
    $flash = '分类 JSON 导入成功，共 ' . $count . ' 条。';
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
            if (isset($decoded['categories']) && is_array($decoded['categories'])) {
                $decoded = $decoded['categories'];
            }
            if (!is_array($decoded)) {
                $error = 'JSON 格式错误，需为数组';
            } else {
                $imported = [];
                $usedIds = [];
                $usedNames = [];
                foreach ($decoded as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $name = trim((string)($row['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $lowerName = strtolower($name);
                    if (isset($usedNames[$lowerName])) {
                        continue;
                    }
                    $id = (int)($row['id'] ?? 0);
                    if ($id <= 0 || isset($usedIds[$id])) {
                        $id = max(array_keys($usedIds) ?: [0]) + 1;
                        while (isset($usedIds[$id])) {
                            $id++;
                        }
                    }
                    $usedIds[$id] = true;
                    $usedNames[$lowerName] = true;
                    $imported[] = ['id' => $id, 'name' => $name];
                }
                if ($imported === []) {
                    $error = '导入失败：JSON 中没有有效分类数据';
                } else {
                    usort($imported, static fn (array $a, array $b): int => (int)$a['id'] <=> (int)$b['id']);
                    write_json('categories.json', $imported);
                    header('Location: /admin/categories.php?import=ok&count=' . count($imported));
                    exit;
                }
            }
        }
    } elseif (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        $beforeCount = count($categories);
        $categories = array_values(array_filter($categories, function ($item) use ($deleteId) {
            return (int)($item['id'] ?? 0) !== $deleteId;
        }));
        if ($beforeCount === count($categories)) {
            $error = '要删除的分类不存在';
        } else {
            $domains = read_json('domains.json', []);
            foreach ($domains as $index => $domain) {
                $domainCategoryIds = array_map('intval', $domain['categories'] ?? []);
                $domains[$index]['categories'] = array_values(array_filter(
                    $domainCategoryIds,
                    static fn (int $cid): bool => $cid !== $deleteId
                ));
            }
            write_json('domains.json', $domains);
            write_json('categories.json', $categories);
            header('Location: /admin/categories.php');
            exit;
        }
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $error = '分类名称不能为空';
        } elseif (function_exists('mb_strlen') ? mb_strlen($name) > 50 : strlen($name) > 50) {
            $error = '分类名称最多 50 个字符';
        } else {
            $exists = false;
            foreach ($categories as $item) {
                $old = strtolower(trim((string)($item['name'] ?? '')));
                if ($old !== '' && $old === strtolower($name)) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                $error = '分类名称已存在';
            } else {
            $categories[] = [
                'id' => max(array_column($categories, 'id') ?: [0]) + 1,
                'name' => $name,
            ];
                write_json('categories.json', $categories);
                header('Location: /admin/categories.php');
                exit;
            }
        }
    }
}

admin_header('关键词分类');
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
            <label class="form-label">导入分类 JSON</label>
            <input class="form-control" type="file" name="json_file" accept=".json,application/json" required>
        </div>
        <div class="col-md-6 d-flex align-items-end gap-2">
            <button class="btn btn-outline-primary" type="submit">导入 JSON</button>
            <a class="btn btn-outline-secondary" href="/admin/categories.php?action=export_json">导出 JSON</a>
        </div>
    </form>
    <form class="row g-3 mb-4" method="post">
        <?php echo csrf_input(); ?>
        <div class="col-md-6">
            <label class="form-label">分类名称</label>
            <input class="form-control" type="text" name="name" placeholder="例如 人工智能">
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <button class="btn btn-primary" type="submit">添加分类</button>
        </div>
    </form>

    <table class="table table-hover admin-table">
        <thead>
            <tr>
                <th>名称</th>
                <th class="text-end">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?php echo h($category['name'] ?? ''); ?></td>
                    <td class="text-end">
                        <form method="post" class="d-inline" onsubmit="return confirm('确认删除该分类吗？')">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="delete_id" value="<?php echo (int)($category['id'] ?? 0); ?>">
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
