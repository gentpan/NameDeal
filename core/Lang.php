<?php
/**
 * NameDeal Language Helper
 */
class Lang {
    private static $instance = null;
    private $lang = 'zh';
    private $data = [];

    private function __construct() {
        $this->data = [
            'zh' => [
                'domain_parking' => '域名停放',
                'domain_management' => '域名管理后台',
                'admin_login' => '管理后台登录',
                'password' => '管理员密码',
                'login' => '登录',
                'logout' => '退出登录',
                'submit' => '提交',
                'cancel' => '取消',
                'save' => '保存',
                'edit' => '修改',
                'delete' => '删除',
                'add' => '添加',
                'price' => '参考价格',
                'your_offer' => '您的出价',
                'inquiry' => '购买咨询',
                'contact' => '联系我们',
                'send' => '发送',
                'name' => '姓名',
                'email' => '邮箱',
                'subject' => '主题',
                'message' => '留言内容',
                'whois_query' => 'WHOIS 一键查询',
                'whois_result' => 'WHOIS 查询结果',
                'close' => '关闭',
                'more_domains' => '更多域名',
                'domain_intro' => '域名介绍',
                'verification' => '人类验证',
                'verify_human' => '验证通过',
                'price_changed' => '价格已修改',
                'test_email' => '测试邮件 - 域名后台',
                'settings' => '站点设置',
                'email_settings' => '邮件设置',
                'domain_list' => '域名列表',
                'stats' => '访问统计',
                'footer_settings' => '页脚设置',
                'value_cards' => '价值卡片',
                'language' => '界面语言',
                'zh' => '中文',
                'en' => 'English',
            ],
            'en' => [
                'domain_parking' => 'Domain Parking',
                'domain_management' => 'Domain Management',
                'admin_login' => 'Admin Login',
                'password' => 'Password',
                'login' => 'Login',
                'logout' => 'Logout',
                'submit' => 'Submit',
                'cancel' => 'Cancel',
                'save' => 'Save',
                'edit' => 'Edit',
                'delete' => 'Delete',
                'add' => 'Add',
                'price' => 'Reference Price',
                'your_offer' => 'Your Offer',
                'inquiry' => 'Inquiry',
                'contact' => 'Contact Us',
                'send' => 'Send',
                'name' => 'Name',
                'email' => 'Email',
                'subject' => 'Subject',
                'message' => 'Message',
                'whois_query' => 'WHOIS Lookup',
                'whois_result' => 'WHOIS Result',
                'close' => 'Close',
                'more_domains' => 'More Domains',
                'domain_intro' => 'Domain Introduction',
                'verification' => 'Human Verification',
                'verify_human' => 'Verified',
                'price_changed' => 'Price Updated',
                'test_email' => 'Test Email - Domain Admin',
                'settings' => 'Site Settings',
                'email_settings' => 'Email Settings',
                'domain_list' => 'Domain List',
                'stats' => 'Statistics',
                'footer_settings' => 'Footer Settings',
                'value_cards' => 'Value Cards',
                'language' => 'Language',
                'zh' => '中文',
                'en' => 'English',
            ],
        ];

        // Detect language from settings or cookie
        $this->detectLanguage();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function detectLanguage(): void {
        // Check cookie first (user preference)
        if (isset($_COOKIE['nd_lang']) && in_array($_COOKIE['nd_lang'], ['zh', 'en'], true)) {
            $this->lang = $_COOKIE['nd_lang'];
            return;
        }
        // Check settings file
        $settingsFile = __DIR__ . '/../data/site_settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            if (isset($settings['language']) && in_array($settings['language'], ['zh', 'en'], true)) {
                $this->lang = $settings['language'];
                return;
            }
        }
        // Check browser language
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (stripos($acceptLang, 'zh') !== false) {
            $this->lang = 'zh';
            return;
        }
        $this->lang = 'en';
    }

    public function get(string $key): string {
        return $this->data[$this->lang][$key] ?? $key;
    }

    public function getLang(): string {
        return $this->lang;
    }

    public function setLang(string $lang): void {
        if (in_array($lang, ['zh', 'en'], true)) {
            $this->lang = $lang;
        }
    }
}

// Helper function
function nd__(string $key): string {
    return Lang::getInstance()->get($key);
}

function nd_lang(): string {
    return Lang::getInstance()->getLang();
}
