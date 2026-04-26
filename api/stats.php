<?php
/**
 * 统计API接口
 * 供前台页面获取域名访问统计数据
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../core/StatsTracker.php';

// 获取请求参数
$domain = isset($_GET['domain']) ? trim($_GET['domain']) : '';
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;

// 验证参数
if (empty($domain)) {
    // 如果没有提供域名，尝试从HTTP_HOST获取
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
}

if (empty($domain)) {
    echo json_encode([
        'success' => false,
        'message' => '缺少域名参数'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证天数范围
if ($days < 1 || $days > 365) {
    $days = 30;
}

try {
    $statsTracker = new StatsTracker();
    $stats = $statsTracker->getStats($domain, $days);
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '获取统计数据失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

