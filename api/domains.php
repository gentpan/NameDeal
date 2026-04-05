<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

$domains = read_json('domains.json', []);
$categories = read_json('categories.json', []);

$categoryName = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$suffixFilter = isset($_GET['suffix']) ? strtolower(trim((string)$_GET['suffix'])) : '';

$categoryId = null;
if ($categoryName !== '') {
    foreach ($categories as $category) {
        if ((string)($category['name'] ?? '') === $categoryName) {
            $categoryId = (int)($category['id'] ?? 0);
            break;
        }
    }
}

$filtered = [];
foreach ($domains as $domain) {
    if ($categoryId !== null) {
        $domainCategories = $domain['categories'] ?? [];
        if (!in_array($categoryId, $domainCategories, true)) {
            continue;
        }
    }

    if ($suffixFilter !== '') {
        $suffix = strtolower((string)($domain['suffix'] ?? ''));
        if ($suffix !== $suffixFilter) {
            continue;
        }
    }

    $regDate = (string)($domain['reg_date'] ?? '');
    $expDate = (string)($domain['exp_date'] ?? '');
    $years = isset($domain['reg_years']) ? (int)$domain['reg_years'] : 0;
    if ($years <= 0) {
        $years = compute_years($regDate !== '' ? $regDate : null, $expDate !== '' ? $expDate : null) ?? 0;
    }
    $yearBadge = get_year_badge_label($years > 0 ? $years : null);
    $yearBadgeClass = get_year_badge_class($years > 0 ? $years : null);

    $filtered[] = [
        'id' => (int)($domain['id'] ?? 0),
        'domain' => (string)($domain['domain'] ?? ''),
        'type' => (string)($domain['type'] ?? ''),
        'platform' => (string)($domain['platform'] ?? ''),
        'regDate' => $regDate,
        'description' => (string)($domain['description'] ?? ''),
        'rating' => (string)($domain['rating'] ?? ''),
        'badge' => (string)($domain['badge'] ?? ''),
        'suffix' => (string)($domain['suffix'] ?? ''),
        'composition' => (string)($domain['composition'] ?? ''),
        'expDate' => $expDate,
        'regYears' => $years,
        'regYearsBadge' => $yearBadge,
        'regYearsBadgeClass' => $yearBadgeClass,
        'intro' => (string)($domain['intro'] ?? ''),
        'price_type' => (string)($domain['price_type'] ?? ''),
        'price' => (string)($domain['price'] ?? ''),
        'sale_status' => (string)($domain['sale_status'] ?? ''),
        'buyLink' => (string)($domain['buy_link'] ?? ''),
    ];
}

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo 'const domainData = ' . json_encode($filtered, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';';
