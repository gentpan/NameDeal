<?php

/**
 * 主入口文件
 * 处理域名识别、配置加载和页面渲染
 */

// 引入核心类
require_once __DIR__ . '/core/DomainConfig.php';
require_once __DIR__ . '/core/EmailHandler.php';
require_once __DIR__ . '/core/StatsTracker.php';

// 初始化域名配置
$domainConfig = new DomainConfig();

// 初始化访问统计（自动记录当前访问）
$statsTracker = new StatsTracker();
$statsTracker->track();

// 处理 AJAX 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');

    require_once __DIR__ . '/core/VerificationCode.php';
    $verificationCode = new VerificationCode();

    switch ($_POST['action']) {
        case 'send_code':
            // 发送验证码
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $emailHandler = new EmailHandler($domainConfig);
            $result = $verificationCode->sendCode($email, $emailHandler, $domainConfig);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;

        case 'verify_code':
            // 验证验证码
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $code = isset($_POST['code']) ? trim($_POST['code']) : '';
            $result = $verificationCode->verifyCode($email, $code);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;

        case 'contact':
            // 提交表单
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $offerPrice = isset($_POST['offer_price']) ? trim($_POST['offer_price']) : '';
            
            // 验证邮箱是否已验证
            if (!$verificationCode->isVerified($email)) {
                echo json_encode([
                    'success' => false,
                    'message' => '请先验证您的邮箱'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $emailHandler = new EmailHandler($domainConfig);
            $result = $emailHandler->sendContactForm([
                'name' => isset($_POST['name']) ? trim($_POST['name']) : '',
                'email' => $email,
                'message' => isset($_POST['message']) ? trim($_POST['message']) : '',
                'offer_price' => $offerPrice
            ]);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
    }
}

// 获取配置信息
$title = $domainConfig->get('title');
$description = $domainConfig->get('description');
$domainIntro = $domainConfig->get('domain_intro'); // 域名介绍
$themeColor = $domainConfig->get('theme_color');
$currentDomain = $domainConfig->getCurrentDomain();
$domainPrice = $domainConfig->get('domain_price');
$siteName = $domainConfig->get('site_name', 'DOMAIN.LS'); // 站点名称，默认为 DOMAIN.LS

// 引入页面模板
include __DIR__ . '/templates/parking.php';
