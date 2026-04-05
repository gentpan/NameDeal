<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

$content = read_json('content.json', []);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'success' => true,
    'data' => $content,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
