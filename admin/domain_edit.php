<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
require_admin();
require __DIR__ . '/_layout.php';

$domains = read_json('domains.json', []);
$categories = read_json('categories.json', []);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current = null;
$error = '';
foreach ($domains as $item) {
    if ((int)($item['id'] ?? 0) === $id) {
        $current = $item;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $domainName = normalize_domain((string)($_POST['domain'] ?? ''));
    $suffix = normalize_suffix((string)($_POST['suffix'] ?? ''));
    if ($suffix === '' && str_contains($domainName, '.')) {
        $parts = explode('.', $domainName);
        $suffix = normalize_suffix((string)end($parts));
    }

    $regDate = trim((string)($_POST['reg_date'] ?? ''));
    $expDate = trim((string)($_POST['exp_date'] ?? ''));
    $whoisRaw = trim((string)($_POST['whois_raw'] ?? ''));

    if ($whoisRaw !== '') {
        $parsed = parse_whois_raw($whoisRaw);
        $regDate = $parsed['reg_date'] ?? $regDate;
        $expDate = $parsed['exp_date'] ?? $expDate;
    }

    if ($regDate !== '') {
        $regDate = parse_date_string($regDate) ?? '';
    }
    if ($expDate !== '') {
        $expDate = parse_date_string($expDate) ?? '';
    }

    $allowedCategoryIds = array_map(static fn ($row): int => (int)($row['id'] ?? 0), $categories);
    $selectedCategoryIds = array_unique(array_map('intval', $_POST['categories'] ?? []));
    $selectedCategoryIds = array_values(array_filter($selectedCategoryIds, static fn (int $cid): bool => in_array($cid, $allowedCategoryIds, true)));

    $buyLink = trim((string)($_POST['buy_link'] ?? ''));
    $buyLink = ($buyLink === '#' ? '' : $buyLink);
    $payloadId = $id ?: (max(array_column($domains, 'id') ?: [0]) + 1);

    $priceType = trim((string)($_POST['price_type'] ?? 'buyer_offer'));
    $allowedPriceTypes = ['buyer_offer', 'specific', 'not_for_sale'];
    if (!in_array($priceType, $allowedPriceTypes, true)) {
        $priceType = 'buyer_offer';
    }

    $saleStatus = trim((string)($_POST['sale_status'] ?? 'holding'));
    $allowedSaleStatuses = ['personal_collection', 'holding', 'coming_soon', 'on_sale', 'negotiating', 'sold'];
    if (!in_array($saleStatus, $allowedSaleStatuses, true)) {
        $saleStatus = 'holding';
    }

    $badge = trim((string)($_POST['badge'] ?? ''));
    $allowedBadges = ['', 'featured', 'new', 'special', 'flash'];
    if (!in_array($badge, $allowedBadges, true)) {
        $badge = '';
    }

    $regYears = compute_years($regDate ?: null, $expDate ?: null);
    $payload = [
        'id' => $payloadId,
        'domain' => $domainName,
        'suffix' => $suffix,
        'type' => trim((string)($_POST['type'] ?? '')),
        'platform' => trim((string)($_POST['platform'] ?? '')),
        'reg_date' => $regDate,
        'exp_date' => $expDate,
        'reg_years' => $regYears,
        'composition' => trim((string)($_POST['composition'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'intro' => trim((string)($_POST['intro'] ?? '')),
        'rating' => trim((string)($_POST['rating'] ?? '')),
        'badge' => $badge,
        'price_type' => $priceType,
        'price' => trim((string)($_POST['price'] ?? '')),
        'sale_status' => $saleStatus,
        'buy_link' => $buyLink,
        'categories' => $selectedCategoryIds,
        'tags' => array_values(array_unique(array_filter(array_map('trim', explode(',', (string)($_POST['tags'] ?? '')))))),
        'whois_raw' => $whoisRaw,
    ];

    if (!is_valid_domain_name($domainName)) {
        $error = '域名格式不正确，请输入如 domain.ls 的格式';
    } elseif (!is_valid_suffix($suffix)) {
        $error = '后缀格式不正确，例如 .com 或 .co.uk';
    } elseif ($buyLink !== '' && !is_valid_http_url($buyLink)) {
        $error = '购买链接必须是 http:// 或 https:// 开头的有效地址';
    } else {
        foreach ($domains as $item) {
            $itemId = (int)($item['id'] ?? 0);
            $itemDomain = normalize_domain((string)($item['domain'] ?? ''));
            if ($itemId !== $payloadId && $itemDomain === $domainName) {
                $error = '该域名已存在，不能重复添加';
                break;
            }
        }

        if ($error === '') {
            $updated = false;
            foreach ($domains as $index => $item) {
                if ((int)($item['id'] ?? 0) === $payload['id']) {
                    $domains[$index] = $payload;
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                $domains[] = $payload;
            }

            write_json('domains.json', $domains);
            header('Location: /admin/domains.php');
            exit;
        }
    }

    $current = $payload;
}

admin_header($current ? '编辑域名' : '新增域名');
?>
<?php
$badges = [
    '' => '无',
    'featured' => '精选域名',
    'new' => '最新推荐',
    'special' => '非卖藏品',
    'flash' => '限时特价',
];
$priceTypes = [
    'buyer_offer' => '买方报价',
    'specific' => '具体价格',
    'not_for_sale' => '非售卖品',
];
$saleStatuses = [
    'personal_collection' => '个人收藏',
    'holding' => '未售持有',
    'coming_soon' => '即将开售',
    'on_sale' => '正在开售',
    'negotiating' => '洽商状态',
    'sold' => '已售出',
];
$currentPriceType = $current['price_type'] ?? 'buyer_offer';
$currentSaleStatus = $current['sale_status'] ?? 'holding';
?>
<div class="domain-edit-layout">
    <div class="domain-edit-main">
        <div class="admin-card">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php endif; ?>
            <form method="post" id="domainEditForm">
                <?php echo csrf_input(); ?>

                <section class="domain-edit-section">
                    <div class="domain-edit-section-title">基础信息</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">域名</label>
                            <input class="form-control" type="text" name="domain" id="domainField" value="<?php echo h($current['domain'] ?? ''); ?>" placeholder="例如 ai.ls" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">后缀</label>
                            <input class="form-control" type="text" name="suffix" id="suffixField" value="<?php echo h($current['suffix'] ?? ''); ?>" placeholder=".com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">域名类型</label>
                            <input class="form-control" type="text" name="type" value="<?php echo h($current['type'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">平台/后缀描述</label>
                            <input class="form-control" type="text" name="platform" id="platformField" value="<?php echo h($current['platform'] ?? ''); ?>">
                        </div>
                    </div>
                </section>

                <section class="domain-edit-section">
                    <div class="domain-edit-section-title">交易与时间</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">注册时间</label>
                            <input class="form-control" type="date" name="reg_date" value="<?php echo h($current['reg_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">到期时间</label>
                            <input class="form-control" type="date" name="exp_date" value="<?php echo h($current['exp_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">价格类型</label>
                            <select class="form-select" name="price_type" id="priceTypeField">
                                <?php foreach ($priceTypes as $value => $label): ?>
                                    <option value="<?php echo h($value); ?>" <?php echo ($currentPriceType === $value) ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">价格</label>
                            <input class="form-control" type="text" name="price" id="priceField" value="<?php echo h($current['price'] ?? ''); ?>" placeholder="例如 38000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">在售状态</label>
                            <select class="form-select" name="sale_status" id="saleStatusField">
                                <?php foreach ($saleStatuses as $value => $label): ?>
                                    <option value="<?php echo h($value); ?>" <?php echo ($currentSaleStatus === $value) ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">购买链接</label>
                            <input class="form-control" type="text" name="buy_link" value="<?php echo h($current['buy_link'] ?? ''); ?>" placeholder="https://">
                        </div>
                    </div>
                </section>

                <section class="domain-edit-section">
                    <div class="domain-edit-section-title">展示信息</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">构成类型</label>
                            <input class="form-control" type="text" name="composition" value="<?php echo h($current['composition'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">评分标签</label>
                            <input class="form-control" type="text" name="rating" value="<?php echo h($current['rating'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">卡片标签</label>
                            <select class="form-select" name="badge">
                                <?php foreach ($badges as $value => $label): ?>
                                    <option value="<?php echo h($value); ?>" <?php echo (($current['badge'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">简介描述</label>
                            <input class="form-control" type="text" name="description" value="<?php echo h($current['description'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">域名介绍</label>
                            <textarea class="form-control" name="intro" rows="3"><?php echo h($current['intro'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="domain-edit-section">
                    <div class="domain-edit-section-title">分类与扩展</div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">关键词分类</label>
                            <div class="row">
                                <?php foreach ($categories as $category): ?>
                                    <?php $checked = in_array((int)$category['id'], $current['categories'] ?? [], true) ? 'checked' : ''; ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo (int)$category['id']; ?>" <?php echo $checked; ?>>
                                            <label class="form-check-label"><?php echo h($category['name'] ?? ''); ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">标签（逗号分隔）</label>
                            <input class="form-control" type="text" name="tags" value="<?php echo h(implode(',', $current['tags'] ?? [])); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">WHOIS 原文（可选，保存时自动解析）</label>
                            <textarea class="form-control" name="whois_raw" rows="4"><?php echo h($current['whois_raw'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </section>

                <div class="domain-edit-actions">
                    <button class="btn btn-primary" type="submit">保存</button>
                    <a class="btn btn-outline-secondary" href="/admin/domains.php">返回</a>
                </div>
            </form>
        </div>
    </div>

    <aside class="domain-edit-aside">
        <div class="admin-card">
            <div class="domain-edit-section-title">保存前摘要</div>
            <div class="edit-summary">
                <div class="edit-summary-row"><span>域名</span><strong id="summaryDomain">-</strong></div>
                <div class="edit-summary-row"><span>后缀</span><strong id="summarySuffix">-</strong></div>
                <div class="edit-summary-row"><span>平台描述</span><strong id="summaryPlatform">-</strong></div>
                <div class="edit-summary-row"><span>价格类型</span><strong id="summaryPriceType">-</strong></div>
                <div class="edit-summary-row"><span>价格</span><strong id="summaryPrice">-</strong></div>
                <div class="edit-summary-row"><span>在售状态</span><strong id="summaryStatus">-</strong></div>
            </div>
            <div class="form-text mt-2">点击保存时会先弹出确认摘要。</div>
        </div>
    </aside>
</div>
<script>
    (function () {
        var form = document.getElementById('domainEditForm');
        if (!form) return;

        var map = {
            domain: document.getElementById('domainField'),
            suffix: document.getElementById('suffixField'),
            platform: document.getElementById('platformField'),
            priceType: document.getElementById('priceTypeField'),
            price: document.getElementById('priceField'),
            status: document.getElementById('saleStatusField'),
            outDomain: document.getElementById('summaryDomain'),
            outSuffix: document.getElementById('summarySuffix'),
            outPlatform: document.getElementById('summaryPlatform'),
            outPriceType: document.getElementById('summaryPriceType'),
            outPrice: document.getElementById('summaryPrice'),
            outStatus: document.getElementById('summaryStatus')
        };

        var priceTypeText = {
            buyer_offer: '买方报价',
            specific: '具体价格',
            not_for_sale: '非售卖品'
        };
        var statusText = {
            personal_collection: '个人收藏',
            holding: '未售持有',
            coming_soon: '即将开售',
            on_sale: '正在开售',
            negotiating: '洽商状态',
            sold: '已售出'
        };

        function val(v) {
            var t = (v || '').trim();
            return t === '' ? '-' : t;
        }

        function syncSummary() {
            map.outDomain.textContent = val(map.domain.value);
            map.outSuffix.textContent = val(map.suffix.value);
            map.outPlatform.textContent = val(map.platform.value);
            map.outPriceType.textContent = priceTypeText[map.priceType.value] || '-';
            map.outPrice.textContent = val(map.price.value);
            map.outStatus.textContent = statusText[map.status.value] || '-';
        }

        [map.domain, map.suffix, map.platform, map.priceType, map.price, map.status].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input', syncSummary);
            el.addEventListener('change', syncSummary);
        });
        syncSummary();

        form.addEventListener('submit', function (e) {
            var confirmText = '请确认保存以下内容：\n' +
                '域名：' + map.outDomain.textContent + '\n' +
                '后缀：' + map.outSuffix.textContent + '\n' +
                '价格类型：' + map.outPriceType.textContent + '\n' +
                '价格：' + map.outPrice.textContent + '\n' +
                '在售状态：' + map.outStatus.textContent;
            if (!window.confirm(confirmText)) {
                e.preventDefault();
            }
        });
    })();
</script>
<?php
admin_footer();
