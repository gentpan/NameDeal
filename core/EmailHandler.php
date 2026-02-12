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
    private $defaultToEmail = '';
    private $fixedMailBlue = '#0066FC';

    public function __construct($domainConfig)
    {
        $this->domainConfig = $domainConfig;
        $this->themeColor = $domainConfig->get('theme_color', '#0066FC');
        $settings = $this->readJsonFile(__DIR__ . '/../data/email_settings.json');
        $configuredTo = trim((string)($settings['default_to_email'] ?? ''));
        if ($configuredTo !== '' && filter_var($configuredTo, FILTER_VALIDATE_EMAIL)) {
            $this->defaultToEmail = $configuredTo;
        }
    }

    private function readJsonFile($file, $default = [])
    {
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: $default;
        }
        return $default;
    }

    private function normalizeSiteName($siteName)
    {
        $clean = trim(str_replace('域名停放', '', (string)$siteName));
        return $clean !== '' ? $clean : 'NameDeal';
    }

    /**
     * 获取邮件Logo SVG（使用与前端页面相同的logo）
     */
    private function getLogoHtml($primaryColor)
    {
        $domain = $this->domainConfig->getCurrentDomain();
        $scheme = 'https';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'http' ? 'http' : 'https';
        } elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
            $scheme = strtolower((string)$_SERVER['REQUEST_SCHEME']) === 'http' ? 'http' : 'https';
        } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        } elseif (!empty($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '80') {
            $scheme = 'http';
        }

        $logoUrl = htmlspecialchars($scheme . '://' . $domain . '/assets/images/logo.png', ENT_QUOTES, 'UTF-8');
        return '<img src="' . $logoUrl . '" alt="Logo" width="80" height="80" style="display:block; width:80px; height:80px; border-radius:14px; object-fit:contain; border:0; outline:none; text-decoration:none;">';
    }

    /**
     * 获取深色模式样式
     */
    private function getDarkModeStyles($primaryColor)
    {
        return '.logo { display: inline-block; width: 100%; height: 100%; }
            .logo svg { width: 100%; height: 100%; display: block; }
            .header-main { table-layout: fixed; }
            .header-logo-cell { width: 88px; font-size: 0; line-height: 0; text-align: right; padding-right: 8px; }
            .header-logo-wrap { width: 80px; height: 80px; display: inline-block; vertical-align: middle; }
            .email-container { width: 100% !important; }
            .brand-divider { background-color: ' . $primaryColor . ' !important; }
            .email-title, .email-heading { color: #1a1a1a; }
            .email-text { color: #666666; }
            .email-muted { color: #666666; }
            .email-value { color: #1a1a1a; }
            .email-accent { color: ' . $primaryColor . '; }
            a.email-link { color: ' . $primaryColor . '; text-decoration: none; }
            .email-list li { color: #666666; }
            .email-footer-separator { color: #cccccc; }
            @media only screen and (max-width: 640px) {
                .email-wrapper { padding: 16px 10px !important; }
                .email-header { padding: 24px 20px 20px !important; }
                .email-content { padding: 24px 20px !important; }
                .email-footer { padding: 20px !important; }
                .mobile-title { font-size: 24px !important; line-height: 1.3 !important; padding-right: 8px !important; }
                .mobile-price { font-size: 28px !important; }
                .header-logo-cell { width: 64px !important; text-align: right !important; padding-right: 6px !important; }
                .header-logo-wrap { width: 56px !important; height: 56px !important; }
                .mobile-stack,
                .mobile-stack tbody,
                .mobile-stack tr,
                .mobile-stack td,
                .mobile-stack-cell {
                    display: block !important;
                    width: 100% !important;
                }
                .mobile-gap {
                    display: none !important;
                    width: 0 !important;
                    padding: 0 !important;
                }
            }
            @media (prefers-color-scheme: dark) {
                html { background-color: #000000 !important; }
                body, .email-body { background-color: #000000 !important; }
                .email-wrapper { background-color: #000000 !important; }
                table.email-container, .email-container { background-color: #0b0b0b !important; color: #f5f5f5 !important; }
                .email-container { background-color: #0b0b0b !important; color: #f5f5f5 !important; }
                td.email-header, .email-header { background-color: #0b0b0b !important; }
                td.email-content, .email-content { background-color: #0f0f0f !important; border-color: #222222 !important; }
                .code-box, .info-card, .info-row, td.info-row { background-color: #151515 !important; border-color: #2a2a2a !important; color: #f5f5f5 !important; }
                .info-highlight { background-color: #121212 !important; }
                td.price-highlight, .price-highlight { background-color: #101010 !important; }
                .text-secondary { color: #b8b8b8 !important; }
                .footer-text { color: #9a9a9a !important; }
                td.email-footer, .email-footer { background-color: #0a0a0a !important; border-color: #222222 !important; }
                .email-title, .email-heading { color: #f5f5f5 !important; }
                .email-text { color: #dddddd !important; }
                .email-muted { color: #b8b8b8 !important; }
                .email-value { color: #f5f5f5 !important; }
                .email-list li { color: #d2d2d2 !important; }
                .email-footer-separator { color: #666666 !important; }
                .email-header h1, .email-content h2, .email-content h3 { color: #f5f5f5 !important; }
                .email-content p, .email-content div, .email-content span, .email-content li { color: #dddddd !important; }
                .email-accent { color: ' . $primaryColor . ' !important; }
                a, a.email-link { color: ' . $primaryColor . ' !important; text-decoration: none !important; }
                strong { color: ' . $primaryColor . ' !important; }
            }
            /* Outlook/部分移动端邮件客户端深色模式 */
            [data-ogsc] body, [data-ogsb] body, [data-ogsc] .email-body, [data-ogsb] .email-body { background-color: #000000 !important; }
            [data-ogsc] .email-wrapper, [data-ogsb] .email-wrapper { background-color: #000000 !important; }
            [data-ogsc] table.email-container, [data-ogsb] table.email-container, [data-ogsc] .email-container, [data-ogsb] .email-container { background-color: #0b0b0b !important; color: #f5f5f5 !important; }
            [data-ogsc] td.email-header, [data-ogsb] td.email-header, [data-ogsc] .email-header, [data-ogsb] .email-header { background-color: #0b0b0b !important; }
            [data-ogsc] td.email-content, [data-ogsb] td.email-content, [data-ogsc] .email-content, [data-ogsb] .email-content { background-color: #0f0f0f !important; border-color: #222222 !important; }
            [data-ogsc] .info-card, [data-ogsb] .info-card, [data-ogsc] .info-row, [data-ogsb] .info-row, [data-ogsc] td.info-row, [data-ogsb] td.info-row, [data-ogsc] .code-box, [data-ogsb] .code-box { background-color: #151515 !important; border-color: #2a2a2a !important; color: #f5f5f5 !important; }
            [data-ogsc] .info-highlight, [data-ogsb] .info-highlight { background-color: #121212 !important; }
            [data-ogsc] td.price-highlight, [data-ogsb] td.price-highlight, [data-ogsc] .price-highlight, [data-ogsb] .price-highlight { background-color: #101010 !important; }
            [data-ogsc] td.email-footer, [data-ogsb] td.email-footer, [data-ogsc] .email-footer, [data-ogsb] .email-footer { background-color: #0a0a0a !important; border-color: #222222 !important; }
            [data-ogsc] .email-title, [data-ogsb] .email-title, [data-ogsc] .email-heading, [data-ogsb] .email-heading { color: #f5f5f5 !important; }
            [data-ogsc] .email-text, [data-ogsb] .email-text { color: #dddddd !important; }
            [data-ogsc] .email-muted, [data-ogsb] .email-muted { color: #b8b8b8 !important; }
            [data-ogsc] .email-list li, [data-ogsb] .email-list li { color: #d2d2d2 !important; }
            [data-ogsc] .text-secondary, [data-ogsb] .text-secondary { color: #b8b8b8 !important; }
            [data-ogsc] .footer-text, [data-ogsb] .footer-text { color: #9a9a9a !important; }
            [data-ogsc] .email-value, [data-ogsb] .email-value { color: #f5f5f5 !important; }
            [data-ogsc] .email-accent, [data-ogsb] .email-accent { color: ' . $primaryColor . ' !important; }
            [data-ogsc] .brand-divider, [data-ogsb] .brand-divider { background-color: ' . $primaryColor . ' !important; }
            [data-ogsc] a, [data-ogsb] a, [data-ogsc] a.email-link, [data-ogsb] a.email-link { color: ' . $primaryColor . ' !important; text-decoration: none !important; }';
    }

    /**
     * 获取邮件Footer
     */
    private function getFooterHtml($domain, $primaryColor, $siteName)
    {
        $domainLowerRaw = strtolower((string)$domain);
        $domainLower = htmlspecialchars($domainLowerRaw, ENT_QUOTES, 'UTF-8');
        $siteNameClean = htmlspecialchars($this->normalizeSiteName($siteName), ENT_QUOTES, 'UTF-8');
        $fixedBlue = $this->fixedMailBlue;
        $contactEmailRaw = $this->defaultToEmail !== '' ? $this->defaultToEmail : ('info@' . $domainLowerRaw);
        $contactEmail = htmlspecialchars($contactEmailRaw, ENT_QUOTES, 'UTF-8');
        return '<tr>
            <td class="email-footer" style="padding: 30px 40px; text-align: center; background-color: #f8f9fa; border-top: 1px solid #e0e0e0;">
                <p style="margin: 0 0 12px; color: #999999; font-size: 12px; line-height: 1.6;" class="footer-text">
                    本邮件为系统自动发送，请勿直接回复。如需帮助或反馈，请通过以下方式联系我们：
                </p>
                <p style="margin: 0 0 8px; color: #666666; font-size: 12px;" class="footer-text">
                    <a href="mailto:' . $contactEmail . '" style="color: ' . $fixedBlue . '; text-decoration: none !important; font-weight: 500;">' . $contactEmail . '</a>
                    <span class="email-footer-separator" style="color: #cccccc; margin: 0 8px;">|</span>
                    <a href="https://' . $domainLower . '/#contact" style="color: ' . $fixedBlue . '; text-decoration: none !important; font-weight: 500;">联系我们</a>
                </p>
                <p style="margin: 0; color: #999999; font-size: 11px;" class="footer-text">
                    ' . date('Y') . ' © ' . $siteNameClean . ' | 保留所有权利
                </p>
            </td>
        </tr>';
    }

    /**
     * 获取验证码邮件模板
     * @param string $code 验证码
     * @return string HTML邮件内容
     */
    public function getVerificationCodeTemplate($code)
    {
        $domain = $this->domainConfig->getCurrentDomain();
        $domainEscaped = htmlspecialchars($domain, ENT_QUOTES, 'UTF-8');
        $primaryColor = $this->fixedMailBlue;
        $siteName = $this->normalizeSiteName($this->domainConfig->get('site_name', 'NameDeal'));
        $darkStyles = $this->getDarkModeStyles($primaryColor);
        $logo = $this->getLogoHtml($primaryColor);
        $footer = $this->getFooterHtml($domain, $primaryColor, $siteName);
        $codeEscaped = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>验证码</title>
    <style>' . $darkStyles . '</style>
</head>
<body class="email-body" style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'SF Pro Display\', \'Segoe UI\', \'PingFang SC\', \'Hiragino Sans GB\', sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="email-wrapper" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td class="email-header" style="padding: 40px 40px 30px; background-color: #ffffff;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="header-main">
                                <tr>
                                    <td class="header-title-cell" style="vertical-align: middle; padding-right: 12px;"><h1 class="mobile-title email-title" style="margin: 0; color: #1a1a1a; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">验证码</h1></td>
                                    <td class="header-logo-cell" width="88" style="width: 88px; min-width: 88px; max-width: 88px; vertical-align: middle; text-align: right; padding-right: 8px;"><div class="header-logo-wrap">' . $logo . '</div></td>
                                </tr>
                            </table>
                            <div class="brand-divider" style="margin-top: 30px; height: 3px; background-color: ' . $primaryColor . '; border-radius: 2px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding: 40px; background-color: #ffffff; border: none;">
                            <p class="email-text" style="margin: 0 0 24px; color: #666666; font-size: 16px; line-height: 1.7;">
                                您正在申请购买域名 <a class="email-link email-accent" href="https://' . $domainEscaped . '" style="color: ' . $primaryColor . '; text-decoration: none !important; font-weight: 700;">' . $domainEscaped . '</a>，请在验证码输入框中输入以下验证码：
                            </p>
                            <div class="code-box" style="margin: 0 auto; text-align: center; padding: 24px; background-color: #f8f9fa; border: 2px dashed ' . $primaryColor . '; border-radius: 10px;">
                                <div class="email-accent" style="font-size: 36px; font-weight: 700; color: ' . $primaryColor . '; letter-spacing: 8px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, \'Liberation Mono\', \'Courier New\', monospace;">' . $codeEscaped . '</div>
                            </div>
                            <p class="email-muted" style="margin: 24px 0 0; color: #999999; font-size: 14px; line-height: 1.6;">
                                验证码有效期为 60 秒，请尽快使用。如非本人操作，请忽略此邮件。
                            </p>
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
     * 获取购买咨询确认邮件模板（发送给客户）
     * @param array $formData 表单数据
     * @return string HTML邮件内容
     */
    public function getConfirmationTemplate($formData)
    {
        $domain = $this->domainConfig->getCurrentDomain();
        $primaryColor = $this->fixedMailBlue;
        $siteName = $this->normalizeSiteName($this->domainConfig->get('site_name', 'NameDeal'));
        $name = htmlspecialchars($formData['name']);
        $domainEscaped = htmlspecialchars($domain, ENT_QUOTES, 'UTF-8');
        $domainAnchor = '<a href="https://' . $domainEscaped . '" style="color: ' . $primaryColor . '; text-decoration: none !important; font-weight: 700;">' . $domainEscaped . '</a>';
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
    <meta name="supported-color-schemes" content="light dark">
    <title>购买咨询确认</title>
    <style>' . $darkStyles . '</style>
</head>
<body class="email-body" style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'SF Pro Display\', \'Segoe UI\', \'PingFang SC\', \'Hiragino Sans GB\', sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="email-wrapper" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td class="email-header" style="padding: 40px 40px 30px; background-color: #ffffff;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="header-main">
                                <tr>
                                    <td class="header-title-cell" style="vertical-align: middle; padding-right: 12px;"><h1 class="mobile-title email-title" style="margin: 0; color: #1a1a1a; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">购买咨询已收到</h1></td>
                                    <td class="header-logo-cell" width="88" style="width: 88px; min-width: 88px; max-width: 88px; vertical-align: middle; text-align: right; padding-right: 8px;"><div class="header-logo-wrap">' . $logo . '</div></td>
                                </tr>
                            </table>
                            <div class="brand-divider" style="margin-top: 30px; height: 3px; background-color: ' . $primaryColor . '; border-radius: 2px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding: 40px; background-color: #ffffff; border: none;">
                            <h2 class="email-heading" style="margin: 0 0 20px; color: #1a1a1a; font-size: 20px; font-weight: 600;">尊敬的 ' . $name . '，</h2>
                            <p class="text-secondary email-text" style="margin: 0 0 24px; color: #666666; font-size: 16px; line-height: 1.6;">
                                感谢您对域名 ' . $domainAnchor . ' 的关注和购买咨询。我们已经收到您的信息，我们的团队将在 <strong style="color: ' . $primaryColor . ';">24小时内</strong> 与您取得联系。
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
                                <tr><td><div class="info-card" style="padding: 20px; background-color: #f8f9fa; border-radius: 8px; margin-bottom: 16px;">
                                    <div class="text-secondary email-muted" style="font-size: 14px; color: #666666; margin-bottom: 8px;">咨询域名</div>
                                    <div class="email-accent" style="font-size: 18px; font-weight: 600; color: ' . $primaryColor . ';">' . $domainAnchor . '</div>
                                </div></td></tr>
                                <tr><td><div class="info-card" style="padding: 20px; background-color: #f8f9fa; border-radius: 8px; margin-bottom: 16px;">
                                    <div class="text-secondary email-muted" style="font-size: 14px; color: #666666; margin-bottom: 8px;">您的出价</div>
                                    <div class="email-accent" style="font-size: 18px; font-weight: 600; color: ' . $primaryColor . ';">' . $offerPrice . '</div>
                                </div></td></tr>
                            </table>
                            <div class="info-highlight" style="padding: 24px; background-color: #f0f7ff; border-radius: 8px; margin: 24px 0;">
                                <h3 class="email-heading" style="margin: 0 0 16px; color: ' . $primaryColor . '; font-size: 18px; font-weight: 600;">接下来会发生什么？</h3>
                                <ul class="text-secondary email-list" style="margin: 0; padding-left: 28px; color: #666666; font-size: 14px; line-height: 1.8;">
                                    <li style="margin-bottom: 8px;">我们的专业团队会仔细评估您的出价</li>
                                    <li style="margin-bottom: 8px;">在24小时内，我们会通过邮件或电话与您联系</li>
                                    <li style="margin-bottom: 8px;">我们将为您提供详细的交易流程和后续步骤</li>
                                    <li>如有任何疑问，您可以随时联系我们</li>
                                </ul>
                            </div>
                            <p class="text-secondary email-text" style="margin: 24px 0 0; color: #666666; font-size: 14px; line-height: 1.6;">再次感谢您对我们的信任，期待与您的合作！</p>
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
        $primaryColor = $this->fixedMailBlue;
        $siteName = $this->normalizeSiteName($this->domainConfig->get('site_name', 'NameDeal'));
        $name = htmlspecialchars($formData['name']);
        $email = htmlspecialchars($formData['email']);
        $domainEscaped = htmlspecialchars($domain, ENT_QUOTES, 'UTF-8');
        $domainAnchor = '<a href="https://' . $domainEscaped . '" style="color: ' . $primaryColor . '; text-decoration: none !important; font-weight: 700;">' . $domainEscaped . '</a>';
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
    <meta name="supported-color-schemes" content="light dark">
    <title>新的购买咨询</title>
    <style>' . $darkStyles . '</style>
</head>
<body class="email-body" style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'SF Pro Display\', \'Segoe UI\', \'PingFang SC\', \'Hiragino Sans GB\', sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="email-wrapper" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td class="email-header" style="padding: 40px 40px 30px; background-color: #ffffff;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="header-main">
                                <tr>
                                    <td class="header-title-cell" style="vertical-align: middle; padding-right: 12px;"><h1 class="mobile-title email-title" style="margin: 0; color: #1a1a1a; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">新的购买咨询</h1></td>
                                    <td class="header-logo-cell" width="88" style="width: 88px; min-width: 88px; max-width: 88px; vertical-align: middle; text-align: right; padding-right: 8px;"><div class="header-logo-wrap">' . $logo . '</div></td>
                                </tr>
                            </table>
                            <div class="brand-divider" style="margin-top: 30px; height: 3px; background-color: ' . $primaryColor . '; border-radius: 2px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="price-highlight" style="padding: 24px 40px; background-color: #e6f0ff; text-align: center;">
                            <div class="text-secondary email-muted" style="font-size: 14px; color: #666666; margin-bottom: 8px;">客户出价</div>
                            <div class="mobile-price email-accent" style="font-size: 32px; font-weight: 700; color: ' . $primaryColor . ';">' . $offerPrice . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding: 40px; background-color: #ffffff; border: none;">
                            <h2 class="email-heading" style="margin: 0 0 24px; color: #1a1a1a; font-size: 20px; font-weight: 600;">咨询详情</h2>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td class="info-row" style="padding: 16px 20px; background-color: #f8f9fa; border-radius: 8px; margin-bottom: 12px;">
                                        <div class="text-secondary email-muted" style="font-size: 12px; color: #666666; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">域名</div>
                                        <div class="email-accent" style="font-size: 18px; font-weight: 600; color: ' . $primaryColor . ';">' . $domainAnchor . '</div>
                                    </td>
                                </tr>
                                <tr><td style="height: 8px; line-height: 8px; font-size: 0;">&nbsp;</td></tr>
                            </table>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="mobile-stack">
                                <tr>
                                    <td class="mobile-stack-cell" style="width: 50%; vertical-align: top;">
                                        <div class="info-row" style="padding: 16px 20px; background-color: #f8f9fa; border-radius: 8px;">
                                            <div class="text-secondary email-muted" style="font-size: 12px; color: #666666; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">客户姓名</div>
                                            <div class="email-value" style="font-size: 16px; font-weight: 600; color: #1a1a1a;">' . $name . '</div>
                                        </div>
                                    </td>
                                    <td class="mobile-gap" style="width: 8px;">&nbsp;</td>
                                    <td class="mobile-stack-cell" style="width: 50%; vertical-align: top;">
                                        <div class="info-row" style="padding: 16px 20px; background-color: #f8f9fa; border-radius: 8px;">
                                            <div class="text-secondary email-muted" style="font-size: 12px; color: #666666; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">联系邮箱</div>
                                            <div class="email-accent" style="font-size: 16px; font-weight: 600; color: ' . $primaryColor . ';"><a class="email-link" href="mailto:' . $email . '" style="color: ' . $primaryColor . '; text-decoration: none !important;">' . $email . '</a></div>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <div class="info-card" style="margin: 24px 0 0; padding: 24px; background-color: #f8f9fa; border-radius: 8px;">
                                <div class="text-secondary email-muted" style="font-size: 12px; color: #666666; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">留言内容</div>
                                <div class="email-text" style="font-size: 15px; color: #1a1a1a; line-height: 1.8; white-space: pre-wrap;">' . $message . '</div>
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
