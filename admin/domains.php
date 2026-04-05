<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
require_admin();
require __DIR__ . '/_layout.php';

$domains = read_json('domains.json', []);

if (($_GET['action'] ?? '') === 'export_json') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="domains-' . date('Ymd-His') . '.json"');
    echo json_encode($domains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function extract_rdap_events(array $data): array
{
    $events = $data['events'] ?? [];
    $eventMap = [];
    foreach ($events as $event) {
        $name = strtolower((string)($event['eventAction'] ?? ''));
        $date = (string)($event['eventDate'] ?? '');
        if ($name !== '' && $date !== '' && !isset($eventMap[$name])) {
            $eventMap[$name] = $date;
        }
    }

    $regRaw = (string)($eventMap['registration'] ?? ($eventMap['created'] ?? ''));
    $expRaw = (string)($eventMap['expiration'] ?? ($eventMap['expiry'] ?? ''));

    return [
        'reg_date' => $regRaw !== '' ? (parse_date_string($regRaw) ?? '') : '',
        'exp_date' => $expRaw !== '' ? (parse_date_string($expRaw) ?? '') : '',
    ];
}

function load_local_rdap_events(string $domain): ?array
{
    $samples = read_json('rdap_samples.json', []);
    if (isset($samples[$domain]) && is_array($samples[$domain])) {
        $row = $samples[$domain];
        $events = $row['events'] ?? [];
        $regDate = parse_date_string((string)($events['registration'] ?? '')) ?? '';
        $expDate = parse_date_string((string)($events['expiration'] ?? '')) ?? '';
        return ['reg_date' => $regDate, 'exp_date' => $expDate];
    }
    return null;
}

function fetch_rdap_events(string $domain, ?string &$error = null): ?array
{
    $url = 'https://rdap.org/domain/' . rawurlencode($domain);
    $responseBody = null;
    $status = 0;
    $error = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'],
        ]);
        $responseBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($responseBody === false) {
            $error = curl_error($ch);
        }
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => "Accept: application/rdap+json, application/json\r\n",
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            $error = '请求 RDAP 失败';
        }
    }

    if (!$responseBody || $status >= 400 || $error !== '') {
        $local = load_local_rdap_events($domain);
        if (is_array($local)) {
            return $local;
        }
        $error = $error !== '' ? $error : 'RDAP 查询失败';
        return null;
    }

    $data = json_decode($responseBody, true);
    if (!is_array($data)) {
        $error = 'RDAP 返回数据解析失败';
        return null;
    }

    return extract_rdap_events($data);
}

function normalize_import_domain_row(array $row, array &$usedIds): ?array
{
    $domainName = normalize_domain((string)($row['domain'] ?? ''));
    if (!is_valid_domain_name($domainName)) {
        return null;
    }

    $id = (int)($row['id'] ?? 0);
    if ($id <= 0 || isset($usedIds[$id])) {
        $id = max(array_keys($usedIds) ?: [0]) + 1;
        while (isset($usedIds[$id])) {
            $id++;
        }
    }
    $usedIds[$id] = true;

    $suffix = normalize_suffix((string)($row['suffix'] ?? ''));
    if ($suffix === '' || !is_valid_suffix($suffix)) {
        $parts = explode('.', $domainName);
        if (count($parts) >= 2) {
            $suffix = normalize_suffix(implode('.', array_slice($parts, 1)));
        }
    }
    if ($suffix !== '' && !is_valid_suffix($suffix)) {
        $suffix = '';
    }

    $regDate = parse_date_string((string)($row['reg_date'] ?? '')) ?? '';
    $expDate = parse_date_string((string)($row['exp_date'] ?? '')) ?? '';
    $years = (int)($row['reg_years'] ?? 0);
    if ($years <= 0) {
        $years = compute_years($regDate, $expDate) ?? 0;
    }

    $priceType = (string)($row['price_type'] ?? 'buyer_offer');
    if (!in_array($priceType, ['buyer_offer', 'specific', 'not_for_sale'], true)) {
        $priceType = 'buyer_offer';
    }
    $saleStatus = (string)($row['sale_status'] ?? 'holding');
    if (!in_array($saleStatus, ['personal_collection', 'holding', 'coming_soon', 'on_sale', 'negotiating', 'sold'], true)) {
        $saleStatus = 'holding';
    }
    $badge = (string)($row['badge'] ?? '');
    if (!in_array($badge, ['', 'featured', 'new', 'special', 'flash'], true)) {
        $badge = '';
    }

    $categories = [];
    foreach ((array)($row['categories'] ?? []) as $cid) {
        $n = (int)$cid;
        if ($n > 0) {
            $categories[$n] = $n;
        }
    }
    $tags = [];
    foreach ((array)($row['tags'] ?? []) as $tag) {
        $tag = trim((string)$tag);
        if ($tag !== '') {
            $tags[$tag] = $tag;
        }
    }

    $buyLink = trim((string)($row['buy_link'] ?? ''));
    if ($buyLink !== '' && $buyLink !== '#' && !is_valid_http_url($buyLink)) {
        $buyLink = '';
    }

    return [
        'id' => $id,
        'domain' => $domainName,
        'suffix' => $suffix,
        'type' => trim((string)($row['type'] ?? '')),
        'platform' => trim((string)($row['platform'] ?? '')),
        'composition' => trim((string)($row['composition'] ?? '')),
        'rating' => trim((string)($row['rating'] ?? '')),
        'badge' => $badge,
        'reg_date' => $regDate,
        'exp_date' => $expDate,
        'reg_years' => $years > 0 ? $years : null,
        'price_type' => $priceType,
        'price' => trim((string)($row['price'] ?? '')),
        'buy_link' => $buyLink,
        'sale_status' => $saleStatus,
        'description' => trim((string)($row['description'] ?? '')),
        'intro' => trim((string)($row['intro'] ?? '')),
        'categories' => array_values($categories),
        'tags' => array_values($tags),
        'whois_raw' => trim((string)($row['whois_raw'] ?? '')),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'import_json') {
        $file = $_FILES['json_file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            header('Location: /admin/domains.php?import=invalid_file');
            exit;
        }
        $raw = file_get_contents((string)$file['tmp_name']);
        $decoded = json_decode((string)$raw, true);
        if (isset($decoded['domains']) && is_array($decoded['domains'])) {
            $decoded = $decoded['domains'];
        }
        if (!is_array($decoded)) {
            header('Location: /admin/domains.php?import=invalid_json');
            exit;
        }

        $imported = [];
        $usedIds = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = normalize_import_domain_row($row, $usedIds);
            if ($normalized !== null) {
                $imported[] = $normalized;
            }
        }

        if ($imported === []) {
            header('Location: /admin/domains.php?import=empty');
            exit;
        }
        usort($imported, static fn (array $a, array $b): int => (int)$a['id'] <=> (int)$b['id']);
        write_json('domains.json', $imported);
        header('Location: /admin/domains.php?import=ok&count=' . count($imported));
        exit;
    }

    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        $domains = array_values(array_filter($domains, function ($item) use ($deleteId) {
            return (int)($item['id'] ?? 0) !== $deleteId;
        }));
        write_json('domains.json', $domains);
        header('Location: /admin/domains.php');
        exit;
    }

    if (isset($_POST['sync_id'])) {
        $syncId = (int)$_POST['sync_id'];
        $found = false;
        foreach ($domains as $index => $item) {
            if ((int)($item['id'] ?? 0) !== $syncId) {
                continue;
            }
            $found = true;
            $domainName = normalize_domain((string)($item['domain'] ?? ''));
            if (!is_valid_domain_name($domainName)) {
                header('Location: /admin/domains.php?sync=invalid');
                exit;
            }

            $syncError = null;
            $events = fetch_rdap_events($domainName, $syncError);
            if (!is_array($events)) {
                header('Location: /admin/domains.php?sync=fail');
                exit;
            }

            $regDate = (string)($events['reg_date'] ?? '');
            $expDate = (string)($events['exp_date'] ?? '');
            if ($regDate === '' && $expDate === '') {
                header('Location: /admin/domains.php?sync=empty');
                exit;
            }

            if ($regDate !== '') {
                $domains[$index]['reg_date'] = $regDate;
            }
            if ($expDate !== '') {
                $domains[$index]['exp_date'] = $expDate;
            }
            $domains[$index]['reg_years'] = compute_years(
                (string)($domains[$index]['reg_date'] ?? ''),
                (string)($domains[$index]['exp_date'] ?? '')
            );

            write_json('domains.json', $domains);
            header('Location: /admin/domains.php?sync=ok');
            exit;
        }

        if (!$found) {
            header('Location: /admin/domains.php?sync=notfound');
            exit;
        }
    }
}

$saleStatusMap = [
    'personal_collection' => '个人收藏',
    'holding' => '未售持有',
    'coming_soon' => '即将开售',
    'on_sale' => '正在开售',
    'negotiating' => '洽商状态',
    'sold' => '已售出',
];

$keyword = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$suffixFilter = normalize_suffix((string)($_GET['suffix'] ?? ''));

$statusOptions = array_keys($saleStatusMap);
if ($statusFilter !== '' && !in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = '';
}

$stats = [
    'total' => count($domains),
    'on_sale' => 0,
    'sold' => 0,
    'holding' => 0,
];
foreach ($domains as $item) {
    $status = (string)($item['sale_status'] ?? '');
    if ($status === 'on_sale') {
        $stats['on_sale']++;
    } elseif ($status === 'sold') {
        $stats['sold']++;
    } elseif ($status === 'holding') {
        $stats['holding']++;
    }
}

$suffixOptions = [];
foreach ($domains as $item) {
    $sfx = normalize_suffix((string)($item['suffix'] ?? ''));
    if ($sfx !== '') {
        $suffixOptions[$sfx] = $sfx;
    }
}
ksort($suffixOptions);

$filteredDomains = array_values(array_filter($domains, function (array $item) use ($keyword, $statusFilter, $suffixFilter): bool {
    $domainName = (string)($item['domain'] ?? '');
    $platform = (string)($item['platform'] ?? '');
    $status = (string)($item['sale_status'] ?? '');
    $suffix = normalize_suffix((string)($item['suffix'] ?? ''));

    if ($keyword !== '') {
        $haystack = strtolower($domainName . ' ' . $platform);
        if (!str_contains($haystack, strtolower($keyword))) {
            return false;
        }
    }
    if ($statusFilter !== '' && $status !== $statusFilter) {
        return false;
    }
    if ($suffixFilter !== '' && $suffix !== $suffixFilter) {
        return false;
    }
    return true;
}));

$perPageOptions = [25, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 25;
}

$totalFiltered = count($filteredDomains);
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$currentPage = max(1, (int)($_GET['page'] ?? 1));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $perPage;
$pagedDomains = array_slice($filteredDomains, $offset, $perPage);

$rangeStart = $totalFiltered > 0 ? ($offset + 1) : 0;
$rangeEnd = min($offset + $perPage, $totalFiltered);

$baseQuery = [
    'q' => $keyword,
    'status' => $statusFilter,
    'suffix' => $suffixFilter,
    'per_page' => (string)$perPage,
];
$buildPageUrl = static function (int $page) use ($baseQuery): string {
    $params = array_merge($baseQuery, ['page' => (string)$page]);
    $params = array_filter($params, static fn (string $v): bool => $v !== '');
    return '/admin/domains.php?' . http_build_query($params);
};

admin_header('域名管理');
?>
<?php
$syncStatus = (string)($_GET['sync'] ?? '');
if ($syncStatus === 'ok') {
    echo '<div class="alert alert-success mb-3">WHOIS 同步成功，注册时间/到期时间已更新。</div>';
} elseif ($syncStatus === 'fail') {
    echo '<div class="alert alert-danger mb-3">WHOIS 同步失败，请稍后重试。</div>';
} elseif ($syncStatus === 'empty') {
    echo '<div class="alert alert-warning mb-3">WHOIS 查询成功，但未返回可用的注册/到期时间。</div>';
} elseif ($syncStatus === 'invalid') {
    echo '<div class="alert alert-danger mb-3">域名格式无效，无法执行 WHOIS 同步。</div>';
} elseif ($syncStatus === 'notfound') {
    echo '<div class="alert alert-danger mb-3">未找到要同步的域名记录。</div>';
}
$importStatus = (string)($_GET['import'] ?? '');
if ($importStatus === 'ok') {
    $count = max(0, (int)($_GET['count'] ?? 0));
    echo '<div class="alert alert-success mb-3">域名 JSON 导入成功，共 ' . h((string)$count) . ' 条。</div>';
} elseif ($importStatus === 'invalid_file') {
    echo '<div class="alert alert-danger mb-3">导入失败：请选择有效的 JSON 文件。</div>';
} elseif ($importStatus === 'invalid_json') {
    echo '<div class="alert alert-danger mb-3">导入失败：JSON 格式错误。</div>';
} elseif ($importStatus === 'empty') {
    echo '<div class="alert alert-warning mb-3">导入失败：未读取到有效域名数据。</div>';
}
?>
<div class="admin-stats-grid mb-3">
    <div class="admin-stat-card">
        <div class="admin-stat-label">域名总数</div>
        <div class="admin-stat-value"><?php echo $stats['total']; ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">正在开售</div>
        <div class="admin-stat-value"><?php echo $stats['on_sale']; ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">未售持有</div>
        <div class="admin-stat-value"><?php echo $stats['holding']; ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label">已售出</div>
        <div class="admin-stat-value"><?php echo $stats['sold']; ?></div>
    </div>
</div>

<div class="admin-card mb-3">
    <form class="row g-2 align-items-end" method="get">
        <input type="hidden" name="page" value="1">
        <div class="col-md-4">
            <label class="form-label">关键词</label>
            <input class="form-control" type="text" name="q" value="<?php echo h($keyword); ?>" placeholder="搜索域名或平台描述">
        </div>
        <div class="col-md-2">
            <label class="form-label">在售状态</label>
            <select class="form-select" name="status">
                <option value="">全部</option>
                <?php foreach ($saleStatusMap as $key => $label): ?>
                    <option value="<?php echo h($key); ?>" <?php echo $statusFilter === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">后缀</label>
            <select class="form-select" name="suffix">
                <option value="">全部</option>
                <?php foreach ($suffixOptions as $value): ?>
                    <option value="<?php echo h($value); ?>" <?php echo $suffixFilter === $value ? 'selected' : ''; ?>><?php echo h($value); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">每页显示</label>
            <select class="form-select" name="per_page">
                <?php foreach ($perPageOptions as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary w-100" type="submit">筛选</button>
            <a class="btn btn-outline-secondary w-100" href="/admin/domains.php">重置</a>
        </div>
    </form>
</div>

<div class="domain-list-shell">
    <div class="domain-list-toolbar">
        <div class="domain-list-meta">
            <span class="domain-list-count">共 <?php echo count($filteredDomains); ?> / <?php echo count($domains); ?> 个域名，当前显示 <?php echo $rangeStart; ?>-<?php echo $rangeEnd; ?></span>
            <span class="domain-list-badge">DOMAIN LIST</span>
        </div>
        <div class="d-flex gap-2">
            <form id="domainJsonImportForm" method="post" enctype="multipart/form-data" class="d-inline">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="import_json">
                <input id="domainJsonImportInput" type="file" name="json_file" accept=".json,application/json" style="display:none" required>
                <button class="btn btn-outline-primary" type="button" onclick="document.getElementById('domainJsonImportInput').click()">导入 JSON</button>
            </form>
            <a class="btn btn-outline-secondary" href="/admin/domains.php?action=export_json">导出 JSON</a>
            <a class="btn btn-primary" href="/admin/domain_edit.php">新增域名</a>
        </div>
    </div>

    <div class="domain-table-wrap">
        <table class="domain-table">
            <thead>
                <tr>
                    <th>域名信息</th>
                    <th>后缀</th>
                    <th>注册时间</th>
                    <th>到期时间</th>
                    <th>年限</th>
                    <th>访问购买</th>
                    <th>在售状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$pagedDomains): ?>
                    <tr>
                        <td colspan="8" class="domain-empty">没有匹配结果，请调整筛选条件</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pagedDomains as $domain): ?>
                        <?php
                        $domainId = (int)($domain['id'] ?? 0);
                        $domainName = (string)($domain['domain'] ?? '');
                        $platform = (string)($domain['platform'] ?? '');
                        $buyLink = (string)($domain['buy_link'] ?? '');
                        $statusKey = (string)($domain['sale_status'] ?? '');
                        $statusLabel = $saleStatusMap[$statusKey] ?? ($statusKey ?: '未设置');
                        $years = isset($domain['reg_years']) ? (int)$domain['reg_years'] : 0;
                        if ($years <= 0) {
                            $years = compute_years(
                                (string)($domain['reg_date'] ?? ''),
                                (string)($domain['exp_date'] ?? '')
                            ) ?? 0;
                        }
                        $yearLabel = get_year_badge_label($years > 0 ? $years : null);
                        $yearClass = get_year_badge_class($years > 0 ? $years : null);
                        ?>
                        <tr>
                            <td>
                                <span class="domain-name"><?php echo h($domainName); ?></span>
                                <?php if ($platform !== ''): ?>
                                    <span class="domain-sub"><?php echo h($platform); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo h((string)($domain['suffix'] ?? '')); ?></td>
                            <td><?php echo h((string)($domain['reg_date'] ?? '')); ?></td>
                            <td><?php echo h((string)($domain['exp_date'] ?? '')); ?></td>
                            <td>
                                <?php if ($yearLabel !== ''): ?>
                                    <span class="domain-year-badge <?php echo h($yearClass); ?>"><?php echo h($yearLabel); ?></span>
                                <?php elseif ($years > 0): ?>
                                    <span class="domain-year-badge year-badge-1to2"><?php echo h((string)$years); ?>年</span>
                                <?php else: ?>
                                    <span class="text-body-secondary">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($buyLink !== '' && $buyLink !== '#'): ?>
                                    <a class="domain-link" href="<?php echo h($buyLink); ?>" target="_blank" rel="noopener">访问链接</a>
                                <?php else: ?>
                                    <span class="text-body-secondary">未设置</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="domain-status"><?php echo h($statusLabel); ?></span></td>
                            <td>
                                <div class="d-flex gap-2 domain-actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="/admin/domain_edit.php?id=<?php echo $domainId; ?>">编辑</a>
                                    <form method="post">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="sync_id" value="<?php echo $domainId; ?>">
                                        <button class="btn btn-sm btn-outline-primary domain-sync-btn" type="submit">WHOIS更新</button>
                                    </form>
                                    <form method="post" class="domain-delete-form" onsubmit="return confirm('确认删除该域名吗？')">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo $domainId; ?>">
                                        <button class="btn btn-sm btn-outline-danger domain-delete-btn" type="submit">删除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($totalFiltered > 0): ?>
    <nav class="admin-pagination" aria-label="域名列表分页">
        <a class="admin-page-link <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" href="<?php echo $currentPage <= 1 ? '#' : h($buildPageUrl($currentPage - 1)); ?>">上一页</a>
        <?php
        $start = max(1, $currentPage - 3);
        $end = min($totalPages, $currentPage + 3);
        for ($i = $start; $i <= $end; $i++):
            $active = $i === $currentPage ? 'active' : '';
            ?>
            <a class="admin-page-link <?php echo $active; ?>" href="<?php echo h($buildPageUrl($i)); ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <a class="admin-page-link <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $currentPage >= $totalPages ? '#' : h($buildPageUrl($currentPage + 1)); ?>">下一页</a>
    </nav>
<?php endif; ?>
<script>
    (function () {
        var input = document.getElementById('domainJsonImportInput');
        var form = document.getElementById('domainJsonImportForm');
        if (!input || !form) return;
        input.addEventListener('change', function () {
            if (input.files && input.files.length > 0) {
                form.submit();
            }
        });
    })();
</script>
<?php
admin_footer();
