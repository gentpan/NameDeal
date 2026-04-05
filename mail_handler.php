<?php
declare(strict_types=1);

require_once __DIR__ . '/app/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    echo json_encode(['success' => false, 'message' => '无效数据']);
    exit;
}

$name = trim((string)($payload['name'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$subject = trim((string)($payload['subject'] ?? ''));
$message = trim((string)($payload['message'] ?? ''));
$domain = trim((string)($payload['domain'] ?? ''));

$errors = [];
if ($name === '') {
    $errors[] = '请输入姓名';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '请输入有效邮箱';
}
$messageLength = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
if ($message === '' || $messageLength < 10) {
    $errors[] = '留言内容至少 10 个字符';
}

if ($errors) {
    echo json_encode(['success' => false, 'message' => '校验失败', 'errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

$inquiries = read_json('inquiries.json', []);
$inquiries[] = [
    'time' => date('Y-m-d H:i:s'),
    'name' => $name,
    'email' => $email,
    'subject' => $subject,
    'message' => $message,
    'domain' => $domain,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
];

write_json('inquiries.json', $inquiries);

$sendError = null;
$sent = send_contact_email([
    'name' => $name,
    'email' => $email,
    'subject' => $subject,
    'message' => $message,
    'domain' => $domain,
], $sendError);

if (!$sent) {
    echo json_encode([
        'success' => false,
        'message' => '留言已保存，但邮件发送失败：' . ($sendError ?: '未知错误'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
