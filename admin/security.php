<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
require_admin();

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '/admin/site_settings.php';
if (is_string($query) && $query !== '') {
    $target .= '?' . $query;
}

header('Location: ' . $target);
exit;
