<?php

/**
 * 邮件处理类
 * 负责根据域名配置发送邮件到对应的邮箱地址
 * 包含 SMTP 客户端和邮件模板功能
 */

/**
 * SMTP 客户端类
 * 负责 SMTP 协议通信
 */
class SmtpClient
{
    private $conn;
    private $timeout = 15;

    private function readLine()
    {
        $data = '';
        while ($str = fgets($this->conn, 515)) {
            $data .= $str;
            // RFC: 多行以三位码+"-"开头，最后一行以三位码+空格
            if (preg_match('/^\d{3} /m', $str)) {
                break;
            }
        }
        return $data;
    }

    private function send($cmd)
    {
        fwrite($this->conn, $cmd . "\r\n");
        return $this->readLine();
    }

    private function expectCode($resp, $code)
    {
        return substr($resp, 0, 3) === (string)$code;
    }

    public function sendMail($options)
    {
        $host = $options['host'];
        $port = (int)$options['port'];
        $encryption = $options['encryption']; // none|ssl|tls
        $username = $options['username'];
        $password = $options['password'];
        $from = $options['from'];
        $fromName = $options['fromName'] ?? '';
        $to = $options['to']; // 收件人（必须是后台配置的邮箱）
        $replyTo = $options['replyTo'] ?? null; // 回复地址（可选，用于 Reply-To 头）
        $subject = $options['subject'];
        $body = $options['body'];

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $this->conn = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
        if (!$this->conn) {
            error_log("SMTP connect failed: $errno $errstr");
            return false;
        }
        stream_set_timeout($this->conn, $this->timeout);

        $resp = $this->readLine();
        if (!$this->expectCode($resp, 220)) {
            error_log("SMTP expected 220, got: $resp");
            return false;
        }

        $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $resp = $this->send("EHLO " . $domain);
        if (!$this->expectCode($resp, 250)) {
            // 尝试 HELO
            $resp = $this->send("HELO " . $domain);
            if (!$this->expectCode($resp, 250)) {
                error_log("SMTP EHLO/HELO failed: $resp");
                return false;
            }
        }

        if ($encryption === 'tls') {
            $resp = $this->send("STARTTLS");
            if (!$this->expectCode($resp, 220)) {
                error_log("SMTP STARTTLS failed: $resp");
                return false;
            }
            if (!stream_socket_enable_crypto($this->conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("SMTP enable TLS failed");
                return false;
            }
            // 重新 EHLO
            $resp = $this->send("EHLO " . $domain);
            if (!$this->expectCode($resp, 250)) {
                error_log("SMTP EHLO after STARTTLS failed: $resp");
                return false;
            }
        }

        if ($username !== '') {
            $resp = $this->send('AUTH LOGIN');
            if (!$this->expectCode($resp, 334)) {
                error_log("SMTP AUTH LOGIN failed: $resp");
                return false;
            }
            $resp = $this->send(base64_encode($username));
            if (!$this->expectCode($resp, 334)) {
                error_log("SMTP username rejected: $resp");
                return false;
            }
            $resp = $this->send(base64_encode($password));
            if (!$this->expectCode($resp, 235)) {
                error_log("SMTP password rejected: $resp");
                return false;
            }
        }

        $resp = $this->send('MAIL FROM: <' . $from . '>');
        if (!$this->expectCode($resp, 250)) {
            error_log("SMTP MAIL FROM failed: $resp");
            return false;
        }
        $resp = $this->send('RCPT TO: <' . $to . '>');
        if (!$this->expectCode($resp, 250) && !$this->expectCode($resp, 251)) {
            error_log("SMTP RCPT TO failed: $resp");
            return false;
        }
        $resp = $this->send('DATA');
        if (!$this->expectCode($resp, 354)) {
            error_log("SMTP DATA failed: $resp");
            return false;
        }

        // 构建头与正文（确保UTF-8编码）
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'From: ' . ($fromName ? (mb_encode_mimeheader($fromName, 'UTF-8', 'Q') . ' ') : '') . '<' . $from . '>';
        $headers[] = 'To: <' . $to . '>'; // 收件人：后台配置的邮箱
        if ($replyTo) {
            $headers[] = 'Reply-To: <' . $replyTo . '>'; // 回复地址：客户填写的邮箱
        }
        $headers[] = 'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8', 'Q');
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        // 确保邮件正文使用UTF-8编码
        if (!mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', mb_detect_encoding($body, 'UTF-8, ISO-8859-1, GB2312', true));
        }

        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        // dot-stuffing
        $data = str_replace(["\r\n.\r\n"], ["\r\n..\r\n"], $data);
        fwrite($this->conn, $data . "\r\n.\r\n");
        $resp = $this->readLine();
        if (!$this->expectCode($resp, 250)) {
            error_log("SMTP message not accepted: $resp");
            return false;
        }

        $this->send('QUIT');
        fclose($this->conn);
        return true;
    }
}

/**
 * 邮件模板类
 * 提供统一的邮件模板，支持深色模式适配
 */
class EmailTemplates
{
    private $domainConfig;
    private $themeColor;

    public function __construct($domainConfig)
    {
        $this->domainConfig = $domainConfig;
        $this->themeColor = $domainConfig->get('theme_color', '#0066FC');
    }

    /**
     * 获取邮件Logo SVG（使用与前端页面相同的logo）
     */
    private function getLogoHtml($primaryColor)
    {
        // 确保颜色值被正确转义
        $primaryColorEscaped = htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8');
        return '<div class="logo" style="display: inline-block; width: 80px; height: 80px; line-height: 0;">
            <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; display: block;">
                <path d="M663.04 80H156.16A92.16 92.16 0 0 0 64 172.16v506.88a92.16 92.16 0 0 0 92.16 92.16h506.88a92.16 92.16 0 0 0 92.16-92.16V172.16A92.16 92.16 0 0 0 663.04 80z" fill="' . $primaryColorEscaped . '"></path>
                <path d="M855.04 272H348.16A92.16 92.16 0 0 0 256 364.16v506.88a92.16 92.16 0 0 0 92.16 92.16h506.88a92.16 92.16 0 0 0 92.16-92.16V364.16A92.16 92.16 0 0 0 855.04 272z" fill="' . $primaryColorEscaped . '" opacity=".4"></path>
                <path d="M348.16 280h506.88a84.16 84.16 0 0 1 84.16 84.16v506.88a84.16 84.16 0 0 1-84.16 84.16H348.16a84.16 84.16 0 0 1-84.16-84.16V364.16a84.16 84.16 0 0 1 84.16-84.16z m607.04 84.16a100.16 100.16 0 0 0-100.16-100.16H348.16a100.16 100.16 0 0 0-100.16 100.16v506.88a100.16 100.16 0 0 0 100.16 100.16h506.88a100.16 100.16 0 0 0 100.16-100.16V364.16z" fill="' . $primaryColorEscaped . '" opacity=".2"></path>
                <path d="M754.688 755.36H461.312a6.32 6.32 0 0 0-4.384 10.96C495.248 806.816 548.88 832 608 832c59.12 0 113.856-25.184 151.072-65.68a6.32 6.32 0 0 0-4.384-10.944zM443.792 514.544h328.416c5.472 0 8.752-6.56 5.472-10.944A208.32 208.32 0 0 0 608 416a208.32 208.32 0 0 0-169.68 87.584c-3.296 4.368 0 10.944 5.472 10.944z m328.416 32.848H443.792A43.92 43.92 0 0 0 400 591.152v87.584a43.92 43.92 0 0 0 43.792 43.792h328.416A43.92 43.92 0 0 0 816 678.72v-87.584a43.92 43.92 0 0 0-43.792-43.776z m-224.416 59.104l-16.416 72.256c-2.192 6.56-6.576 8.752-13.152 8.752-6.56 0-10.944-3.28-13.12-8.752l-15.344-50.352-15.312 50.352c-2.192 5.472-6.56 8.752-13.136 8.752s-10.96-3.28-13.152-8.752l-16.416-72.256v-4.368c1.104-5.488 3.28-8.768 8.768-8.768 5.472 0 9.84 3.28 10.944 9.856l10.944 55.84 16.416-56.928c2.192-4.384 5.472-7.68 9.856-7.68 5.472 0 8.752 2.192 9.856 7.68l16.416 56.928 10.944-55.84c1.104-6.56 4.384-9.856 9.856-9.856 5.472 0 8.768 3.28 8.768 8.768 3.28 1.088 3.28 3.28 3.28 4.368z m118.24 0L649.6 678.736c-2.192 6.56-6.56 8.752-13.136 8.752-6.56 0-10.944-3.28-13.136-8.752L608 628.384l-15.328 50.352c-2.192 5.472-6.56 8.752-13.136 8.752-6.56 0-10.944-3.28-13.136-8.752l-16.416-72.256v-4.368c1.088-5.488 3.28-8.768 8.752-8.768 5.472 0 9.856 3.28 10.944 9.856l10.944 55.84 16.432-56.928c2.192-4.384 5.472-7.68 9.856-7.68 5.472 0 8.752 2.192 9.84 7.68l16.432 56.928 10.944-55.84c1.088-6.56 4.384-9.856 9.856-9.856 5.472 0 8.752 3.28 8.752 8.768 4.384 1.088 4.384 3.28 3.28 4.368z m119.312 0l-16.416 72.256c-2.192 6.56-6.56 8.752-13.136 8.752-6.56 0-10.944-3.28-13.136-8.752l-15.328-50.352-15.328 50.352c-2.192 5.472-6.56 8.752-13.136 8.752-6.56 0-10.944-3.28-13.136-8.752l-16.416-72.256v-4.368c1.088-5.488 3.28-8.768 8.752-8.768 5.472 0 9.856 3.28 10.944 9.856l10.944 55.84 16.432-56.928c2.176-4.384 5.472-7.68 9.84-7.68 5.488 0 8.768 2.192 9.856 7.68l16.432 56.928 10.944-55.84c1.088-6.56 4.368-9.856 9.856-9.856 5.472 0 8.752 3.28 8.752 8.768 4.384 1.088 3.28 3.28 3.28 4.368z" fill="#FFFFFF"></path>
            </svg>
        </div>';
    }

    /**
     * 获取深色模式样式
     */
    private function getDarkModeStyles($primaryColor)
    {
        return '.logo { display: inline-block; width: 100%; height: 100%; }
            .logo svg { width: 100%; height: 100%; display: block; }
            @media (prefers-color-scheme: dark) {
                body { background-color: #0a0a0a !important; }
                .email-wrapper { background-color: #0a0a0a !important; }
                .email-container { background-color: #1a1a1a !important; color: #ffffff !important; }
                .email-header { background-color: ' . $primaryColor . ' !important; }
                .email-content { background-color: #2a2a2a !important; border-color: #3a3a3a !important; }
                .code-box, .info-card, .info-row { background-color: #1a1a1a !important; border-color: #3a3a3a !important; color: #ffffff !important; }
                .info-highlight { background-color: #1a3a7a !important; }
                .price-highlight { background-color: #1a3a7a !important; }
                .text-secondary { color: #b0b0b0 !important; }
                .footer-text { color: #808080 !important; }
                .email-footer { background-color: #1a1a1a !important; border-color: #3a3a3a !important; }
                h1, h2, h3 { color: #ffffff !important; }
                p, div { color: #d0d0d0 !important; }
                ul li { color: #b0b0b0 !important; }
                a { color: ' . $primaryColor . ' !important; }
            }';
    }

    /**
     * 获取邮件Footer
     */
    private function getFooterHtml($domain, $primaryColor, $siteName)
    {
        $domainLower = htmlspecialchars(strtolower($domain));
        return '<tr>
            <td class="email-footer" style="padding: 30px 40px; text-align: center; background-color: #f8f9fa; border-top: 1px solid #e0e0e0;">
                <p style="margin: 0 0 12px; color: #999999; font-size: 12px; line-height: 1.6;" class="footer-text">
                    本邮件为系统自动发送，请勿直接回复。如需帮助或反馈，请通过以下方式联系我们：
                </p>
                <p style="margin: 0 0 8px; color: #666666; font-size: 12px;" class="footer-text">
                    <a href="mailto:info@' . $domainLower . '" style="color: ' . $primaryColor . '; text-decoration: none; font-weight: 500;">info@' . $domainLower . '</a>
                    <span style="color: #cccccc; margin: 0 8px;">|</span>
                    <a href="https://' . $domainLower . '/#contact" style="color: ' . $primaryColor . '; text-decoration: none; font-weight: 500;">联系我们</a>
                </p>
                <p style="margin: 0; color: #999999; font-size: 11px;" class="footer-text">
                    ' . date('Y') . ' © ' . htmlspecialchars($siteName) . ' 域名列表 | 保留所有权利
                </p>
            </td>
        </tr>';
    }

    /**
     * 获取验证码邮件模板（简单模式）
     * @param string $code 验证码
     * @return string HTML邮件内容
     */
    public function getVerificationCodeTemplate($code)
    {
        $domain = $this->domainConfig->getCurrentDomain();
        
        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>验证码</title>
</head>
<body style="margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border-radius: 8px;">
        <h2 style="margin: 0 0 20px; color: #333333; font-size: 24px;">验证码</h2>
        <p style="margin: 0 0 30px; color: #666666; font-size: 16px; line-height: 1.6;">
            您正在申请购买域名 <strong>' . htmlspecialchars($domain) . '</strong>，请在验证码输入框中输入以下验证码：
        </p>
        <div style="text-align: center; padding: 30px 0;">
            <div style="display: inline-block; padding: 20px 40px; background-color: #f8f9fa; border: 2px dashed #0066FC; border-radius: 8px;">
                <div style="font-size: 36px; font-weight: bold; color: #0066FC; letter-spacing: 8px; font-family: monospace;">' . htmlspecialchars($code) . '</div>
            </div>
        </div>
        <p style="margin: 30px 0 0; color: #999999; font-size: 14px; line-height: 1.6;">
            验证码有效期为 60 秒，请尽快使用。如非本人操作，请忽略此邮件。
        </p>
    </div>
</body>
</html>';
    }

    /**
     * 获取购买咨询确认邮件模板（发送给客户）
     * @param array $formData 表单数据
     * @return string HTML邮件内容
     */
    public function getConfirmationTemplate($formData)
    {
        $domain = $this->domainConfig->getCurrentDomain();
        $primaryColor = $this->themeColor;
        $siteName = $this->domainConfig->get('site_name', 'DOMAIN.LS');
        $name = htmlspecialchars($formData['name']);
        $offerPrice = !empty($formData['offer_price']) && $formData['offer_price'] > 0 
            ? '¥' . number_format($formData['offer_price'], 2) 
            : '未提供';
        $darkStyles = $this->getDarkModeStyles($primaryColor);
        $logo = $this->getLogoHtml($primaryColor);
        $footer = $this->getFooterHtml($domain, $primaryColor, $siteName);
        
        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>购买咨询确认</title>
    <style>' . $darkStyles . '</style>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'SF Pro Display\', \'Segoe UI\', \'PingFang SC\', \'Hiragino Sans GB\', sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="email-wrapper" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td class="email-header" style="padding: 40px 40px 30px; background-color: #ffffff;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="vertical-align: middle;"><h1 style="margin: 0; color: #1a1a1a; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">购买咨询已收到</h1></td>
                                    <td style="vertical-align: middle; text-align: right;"><div style="width: 80px; height: 80px; display: inline-block;">' . $logo . '</div></td>
                                </tr>
                            </table>
                            <div style="margin-top: 30px; height: 3px; background-color: ' . $primaryColor . '; border-radius: 2px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding: 40px; background-color: #ffffff; border: 1px solid #e0e0e0; border-top: none;">
                            <h2 style="margin: 0 0 20px; color: #1a1a1a; font-size: 20px; font-weight: 600;">尊敬的 ' . $name . '，</h2>
                            <p style="margin: 0 0 24px; color: #666666; font-size: 16px; line-height: 1.6;" class="text-secondary">
                                感谢您对域名 <strong style="color: ' . $primaryColor . ';">' . htmlspecialchars($domain) . '</strong> 的关注和购买咨询。我们已经收到您的信息，我们的团队将在 <strong style="color: ' . $primaryColor . ';">24小时内</strong> 与您取得联系。
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
                                <tr><td><div class="info-card" style="padding: 20px; background-color: #f8f9fa; border-left: 4px solid ' . $primaryColor . '; border-radius: 8px; margin-bottom: 16px;">
                                    <div style="font-size: 14px; color: #666666; margin-bottom: 8px;" class="text-secondary">咨询域名</div>
                                    <div style="font-size: 18px; font-weight: 600; color: ' . $primaryColor . ';">' . htmlspecialchars($domain) . '</div>
                                </div></td></tr>
                                <tr><td><div class="info-card" style="padding: 20px; background-color: #f8f9fa; border-left: 4px solid ' . $primaryColor . '; border-radius: 8px; margin-bottom: 16px;">
                                    <div style="font-size: 14px; color: #666666; margin-bottom: 8px;" class="text-secondary">您的出价</div>
                                    <div style="font-size: 18px; font-weight: 600; color: ' . $primaryColor . ';">' . $offerPrice . '</div>
                                </div></td></tr>
                            </table>
                            <div class="info-highlight" style="padding: 24px; background-color: #f0f7ff; border-radius: 8px; margin: 24px 0;">
                                <h3 style="margin: 0 0 16px; color: ' . $primaryColor . '; font-size: 18px; font-weight: 600; display: flex; align-items: center;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . $primaryColor . '" stroke-width="2" style="margin-right: 8px; flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                    <span>接下来会发生什么？</span>
                                </h3>
                                <ul style="margin: 0; padding-left: 28px; color: #666666; font-size: 14px; line-height: 1.8;" class="text-secondary">
                                    <li style="margin-bottom: 8px;">我们的专业团队会仔细评估您的出价</li>
                                    <li style="margin-bottom: 8px;">在24小时内，我们会通过邮件或电话与您联系</li>
                                    <li style="margin-bottom: 8px;">我们将为您提供详细的交易流程和后续步骤</li>
                                    <li>如有任何疑问，您可以随时联系我们</li>
                                </ul>
                            </div>
                            <p style="margin: 24px 0 0; color: #666666; font-size: 14px; line-height: 1.6;" class="text-secondary">再次感谢您对我们的信任，期待与您的合作！</p>
                        </td>
                    </tr>
                    ' . $footer . '
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * 获取购买咨询通知邮件模板（发送给管理员）
     * @param array $formData 表单数据
     * @return string HTML邮件内容
     */
    public function getAdminNotificationTemplate($formData)
    {
        $domain = $this->domainConfig->getCurrentDomain();
        $primaryColor = $this->themeColor;
        $siteName = $this->domainConfig->get('site_name', 'DOMAIN.LS');
        $name = htmlspecialchars($formData['name']);
        $email = htmlspecialchars($formData['email']);
        $message = nl2br(htmlspecialchars($formData['message']));
        $offerPrice = !empty($formData['offer_price']) && $formData['offer_price'] > 0 
            ? '¥' . number_format($formData['offer_price'], 2) 
            : '未提供出价';
        $darkStyles = $this->getDarkModeStyles($primaryColor);
        $logo = $this->getLogoHtml($primaryColor);
        $footer = $this->getFooterHtml($domain, $primaryColor, $siteName);
        
        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>新的购买咨询</title>
    <style>' . $darkStyles . '</style>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'SF Pro Display\', \'Segoe UI\', \'PingFang SC\', \'Hiragino Sans GB\', sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="email-wrapper" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td class="email-header" style="padding: 40px 40px 30px; background-color: #ffffff;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="vertical-align: middle;"><h1 style="margin: 0; color: #1a1a1a; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">新的购买咨询</h1></td>
                                    <td style="vertical-align: middle; text-align: right;"><div style="width: 80px; height: 80px; display: inline-block;">' . $logo . '</div></td>
                                </tr>
                            </table>
                            <div style="margin-top: 30px; height: 3px; background-color: ' . $primaryColor . '; border-radius: 2px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="price-highlight" style="padding: 24px 40px; background-color: #e6f0ff; text-align: center;">
                            <div style="font-size: 14px; color: #666666; margin-bottom: 8px;" class="text-secondary">客户出价</div>
                            <div style="font-size: 32px; font-weight: 700; color: ' . $primaryColor . ';">' . $offerPrice . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding: 40px; background-color: #ffffff; border: 1px solid #e0e0e0; border-top: none;">
                            <h2 style="margin: 0 0 24px; color: #1a1a1a; font-size: 20px; font-weight: 600;">咨询详情</h2>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr><td class="info-row" style="padding: 16px 20px; background-color: #f8f9fa; border-radius: 8px; margin-bottom: 12px;">
                                    <div style="font-size: 12px; color: #666666; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;" class="text-secondary">域名</div>
                                    <div style="font-size: 18px; font-weight: 600; color: ' . $primaryColor . ';">' . htmlspecialchars($domain) . '</div>
                                </td></tr>
                                <tr><td style="padding: 8px 0;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td class="info-row" style="padding: 16px 20px; background-color: #f8f9fa; border-radius: 8px; width: 48%;">
                                                <div style="font-size: 12px; color: #666666; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;" class="text-secondary">客户姓名</div>
                                                <div style="font-size: 16px; font-weight: 600; color: #1a1a1a;">' . $name . '</div>
                                            </td>
                                            <td style="width: 4%;"></td>
                                            <td class="info-row" style="padding: 16px 20px; background-color: #f8f9fa; border-radius: 8px; width: 48%;">
                                                <div style="font-size: 12px; color: #666666; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;" class="text-secondary">联系邮箱</div>
                                                <div style="font-size: 16px; font-weight: 600; color: ' . $primaryColor . ';"><a href="mailto:' . $email . '" style="color: ' . $primaryColor . '; text-decoration: none;">' . $email . '</a></div>
                                            </td>
                                        </tr>
                                    </table>
                                </td></tr>
                            </table>
                            <div style="margin: 24px 0 0; padding: 24px; background-color: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 12px; color: #666666; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;" class="text-secondary">留言内容</div>
                                <div style="font-size: 15px; color: #1a1a1a; line-height: 1.8; white-space: pre-wrap;">' . $message . '</div>
                            </div>
                        </td>
                    </tr>
                    ' . $footer . '
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}

/**
 * 邮件处理类
 */
class EmailHandler
{
    private $domainConfig;
    private $settings;

    /**
     * 构造函数
     * @param DomainConfig $domainConfig 域名配置对象
     */
    public function __construct(DomainConfig $domainConfig)
    {
        $this->domainConfig = $domainConfig;
        // 读取全局邮件设置
        $file = __DIR__ . '/../data/email_settings.json';
        $this->settings = $this->readJsonFile($file);
    }

    /**
     * 读取JSON文件
     */
    private function readJsonFile($file, $default = [])
    {
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: $default;
        }
        return $default;
    }

    /**
     * 发送邮件
     * 如果后台启用了SMTP，优先使用SMTP发送；如果SMTP失败，回退到PHP mail()函数
     * 如果后台未启用SMTP，直接使用PHP mail()函数
     * @param string $subject 邮件主题
     * @param string $message 邮件内容
     * @param string $fromEmail 发件人邮箱（可选，用于From头）
     * @param string $fromName 发件人姓名（可选，用于From头）
     * @param string $replyToEmail 回复邮箱（可选，用于Reply-To头）
     * @param string $toEmail 收件人邮箱（可选，如果不提供则使用后台默认收件邮箱）
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendEmail($subject, $message, $fromEmail = null, $fromName = null, $replyToEmail = null, $toEmail = null)
    {
        // 获取接收邮箱：如果提供了收件人，使用它；否则使用全局默认收件地址
        if ($toEmail === null) {
            $toEmail = $this->settings['default_to_email'] ?? 'admin@example.com';
        }
        
        // 如果没有提供发件人信息，优先使用全局设置，再回退默认
        if ($fromEmail === null) {
            $fromEmail = $this->settings['from_email'] ?? ('noreply@' . $this->domainConfig->getCurrentDomain());
        }
        if ($fromName === null) {
            $fromName = $this->settings['from_name'] ?? '域名停放系统';
        }
        
        // Reply-To：如果提供了回复邮箱，使用它；否则使用发件人邮箱
        $replyTo = $replyToEmail ?? $fromEmail;

        // 如果后台启用了SMTP，优先使用SMTP发送
        if (!empty($this->settings['smtp_enabled'])) {
            $smtpHost = $this->settings['smtp_host'] ?? '';
            $smtpPort = (int)($this->settings['smtp_port'] ?? 587);
            $smtpEncryption = $this->settings['smtp_encryption'] ?? 'tls'; // none|ssl|tls
            $smtpUser = $this->settings['smtp_username'] ?? '';
            $smtpPass = $this->settings['smtp_password'] ?? '';

            // 如果SMTP主机已配置，尝试使用SMTP发送
            if ($smtpHost) {
                $client = new SmtpClient();
                $ok = $client->sendMail([
                    'host' => $smtpHost,
                    'port' => $smtpPort,
                    'encryption' => $smtpEncryption,
                    'username' => $smtpUser,
                    'password' => $smtpPass,
                    'from' => $fromEmail,
                    'fromName' => $fromName,
                    'to' => $toEmail, // 收件人：后台配置的默认邮箱
                    'replyTo' => $replyTo, // 回复地址：客户填写的邮箱（如果有）
                    'subject' => $subject,
                    'body' => $message,
                ]);
                if ($ok) {
                    return ['success' => true, 'message' => '邮件发送成功（SMTP）'];
                } else {
                    // SMTP发送失败，记录错误并回退到mail()
                    error_log('SMTP发送失败，回退使用PHP mail()函数');
                }
            }
        }

        // 回退使用PHP mail()函数 - 需要正确编码中文
        // 编码主题和发件人名称以支持中文
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'Q');
        $encodedFromName = mb_encode_mimeheader($fromName, 'UTF-8', 'Q');
        
        // 设置邮件头（正确编码中文）
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $replyTo,
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 3'
        ];

        $headersString = implode("\r\n", $headers);
        
        // 确保邮件内容使用UTF-8编码
        $message = mb_convert_encoding($message, 'UTF-8', mb_detect_encoding($message, 'UTF-8, ISO-8859-1', true));
        
        $result = @mail($toEmail, $encodedSubject, $message, $headersString);

        if ($result) {
            $method = !empty($this->settings['smtp_enabled']) ? '（SMTP失败，已回退到PHP mail()）' : '（使用PHP mail()）';
            return [
                'success' => true,
                'message' => '邮件发送成功' . $method
            ];
        } else {
            return [
                'success' => false,
                'message' => '邮件发送失败，请检查服务器邮件配置'
            ];
        }
    }

    /**
     * 发送联系表单邮件
     * @param array $formData 表单数据 ['name' => '', 'email' => '', 'message' => '', 'offer_price' => '']
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendContactForm($formData)
    {
        // 验证必填字段
        if (empty($formData['name']) || empty($formData['email']) || empty($formData['message'])) {
            return [
                'success' => false,
                'message' => '请填写所有必填字段'
            ];
        }

        // 验证邮箱格式
        if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => '邮箱格式不正确'
            ];
        }

        // 构建邮件主题（带上出价信息）
        $baseSubject = '来自 ' . $this->domainConfig->getCurrentDomain() . ' 的购买咨询';
        if (!empty($formData['offer_price']) && $formData['offer_price'] > 0) {
            $subject = $baseSubject . ' - 出价：¥' . number_format($formData['offer_price'], 2);
        } else {
            $subject = $baseSubject;
        }

        // 使用邮件模板构建管理员通知邮件
        $emailTemplates = new EmailTemplates($this->domainConfig);
        $message = $emailTemplates->getAdminNotificationTemplate($formData);

        // 发送邮件到管理员：收件人是后台配置的默认邮箱，Reply-To是用户填写的邮箱
        $result = $this->sendEmail($subject, $message, null, null, $formData['email']);
        
        // 如果发送成功，同时发送确认邮件给客户
        if ($result['success']) {
            $confirmationSubject = '感谢您的购买咨询 - ' . $this->domainConfig->getCurrentDomain();
            $confirmationMessage = $emailTemplates->getConfirmationTemplate($formData);
            
            // 发送确认邮件到客户邮箱
            $this->sendEmail($confirmationSubject, $confirmationMessage, null, null, null, $formData['email']);
        }
        
        return $result;
    }
}
