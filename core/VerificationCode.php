<?php

/**
 * 验证码处理类
 * 负责生成、发送和验证邮箱验证码
 */

class VerificationCode
{
    private $codeDir;
    private $expireTime = 300; // 5分钟有效时间
    private $resendInterval = 60; // 60秒后才能重新发送

    public function __construct()
    {
        $this->codeDir = __DIR__ . '/../data/verification_codes';
        // 确保目录存在
        if (!file_exists($this->codeDir)) {
            mkdir($this->codeDir, 0755, true);
        }
    }

    /**
     * 生成4位数字验证码
     * @return string
     */
    private function generateCode()
    {
        return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function getCodeFileByEmail($email)
    {
        $normalizedEmail = strtolower(trim($email));
        return $this->codeDir . '/' . hash('sha256', $normalizedEmail) . '.json';
    }

    /**
     * 发送验证码到邮箱
     * @param string $email 邮箱地址
     * @param EmailHandler $emailHandler 邮件处理对象
     * @param DomainConfig|null $domainConfig 域名配置对象（可选）
     * @return array ['success' => bool, 'message' => string, 'code' => string|null]
     */
    public function sendCode($email, $emailHandler, $domainConfig = null)
    {
        // 验证邮箱格式
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => '邮箱格式不正确'
            ];
        }

        // 清理过期验证码
        $this->cleanExpiredCodes();

        // 检查是否在60秒内已发送过（重发间隔限制）
        $codeFile = $this->getCodeFileByEmail($email);
        if (file_exists($codeFile)) {
            $data = json_decode(file_get_contents($codeFile), true);
            $timeSinceLastSend = time() - $data['created_at'];
            $timeLeftForResend = $this->resendInterval - $timeSinceLastSend;

            if ($timeLeftForResend > 0) {
                return [
                    'success' => false,
                    'message' => "验证码已发送，请等待 {$timeLeftForResend} 秒后重试",
                    'resend_after' => $timeLeftForResend
                ];
            }
        }

        // 生成验证码
        $code = $this->generateCode();

        // 保存验证码信息
        $data = [
            'email' => $email,
            'code' => $code,
            'created_at' => time(),
            'verified' => false
        ];
        file_put_contents($codeFile, json_encode($data));

        // 使用统一的邮件模板系统
        require_once __DIR__ . '/EmailHandler.php';
        $emailTemplates = new EmailTemplates($domainConfig);
        $message = $emailTemplates->getVerificationCodeTemplate($code);
        $siteName = $domainConfig ? $domainConfig->get('site_name', 'DOMAIN.LS') : 'DOMAIN.LS';
        $subject = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . ' 验证码';

        // 验证码发送到客户填写的邮箱
        $result = $emailHandler->sendEmail($subject, $message, null, null, null, $email);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => '验证码已发送到您的邮箱',
                'expires_in' => $this->expireTime,
                'resend_after' => $this->resendInterval
            ];
        } else {
            return [
                'success' => false,
                'message' => '验证码发送失败：' . $result['message']
            ];
        }
    }

    /**
     * 验证验证码
     * @param string $email 邮箱地址
     * @param string $code 验证码
     * @return array ['success' => bool, 'message' => string]
     */
    public function verifyCode($email, $code)
    {
        $codeFile = $this->getCodeFileByEmail($email);

        if (!file_exists($codeFile)) {
            return [
                'success' => false,
                'message' => '验证码不存在或已过期'
            ];
        }

        $data = json_decode(file_get_contents($codeFile), true);

        // 检查是否过期
        if (time() - $data['created_at'] > $this->expireTime) {
            @unlink($codeFile);
            return [
                'success' => false,
                'message' => '验证码已过期，请重新获取'
            ];
        }

        // 检查是否已验证
        if ($data['verified']) {
            return [
                'success' => false,
                'message' => '验证码已使用过'
            ];
        }

        // 验证码匹配
        if ($data['code'] === $code) {
            // 标记为已验证
            $data['verified'] = true;
            file_put_contents($codeFile, json_encode($data));
            return [
                'success' => true,
                'message' => '验证成功'
            ];
        } else {
            return [
                'success' => false,
                'message' => '验证码错误'
            ];
        }
    }

    /**
     * 清理过期验证码文件
     */
    private function cleanExpiredCodes()
    {
        $files = glob($this->codeDir . '/*.json');
        $now = time();

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($now - $data['created_at'] > $this->expireTime) {
                @unlink($file);
            }
        }
    }

    /**
     * 检查邮箱是否已验证
     * @param string $email 邮箱地址
     * @return bool
     */
    public function isVerified($email)
    {
        $codeFile = $this->getCodeFileByEmail($email);

        if (!file_exists($codeFile)) {
            return false;
        }

        $data = json_decode(file_get_contents($codeFile), true);

        // 检查是否过期
        if (time() - $data['created_at'] > $this->expireTime) {
            return false;
        }

        return $data['verified'] === true;
    }
}
