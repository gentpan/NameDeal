<?php

/**
 * 域名配置管理类
 * 负责识别访问域名并加载对应的配置信息
 * 从数据库加载域名配置
 */

class DomainConfig
{
    private $currentDomain;
    private $domainConfig;
    private $domainManager;
    private $useDatabase = false; // 是否使用数据库

    /**
     * 构造函数
     */
    public function __construct()
    {
        // 获取当前访问的域名
        $this->currentDomain = $this->detectCurrentDomain();

        // 从数据库加载配置
        try {
            require_once __DIR__ . '/DomainManager.php';
            $this->domainManager = new DomainManager();
            $dbConfig = $this->domainManager->getDomainConfig($this->currentDomain);

            if ($dbConfig) {
                $this->domainConfig = $this->formatDbConfig($dbConfig);
                $this->useDatabase = true;
            } else {
                // 数据库中没有，使用默认配置
                $this->domainConfig = $this->getDefaultConfig();
            }
        } catch (Exception $e) {
            // 数据库不可用，使用默认配置
            error_log("数据库加载失败: " . $e->getMessage());
            $this->domainConfig = $this->getDefaultConfig();
        }
    }

    /**
     * 获取默认配置
     */
    private function getDefaultConfig()
    {
        return [
            'title' => '域名出售',
            'description' => '您访问的域名可以出售',
            'theme_color' => '#0065F3',
            'domain_intro' => '',
            'domain_price' => '',
            'logo_url' => '',
        ];
    }

    /**
     * 格式化数据库配置为统一格式
     */
    private function formatDbConfig($dbConfig)
    {
        return [
            'title' => $dbConfig['title'],
            'description' => $dbConfig['description'] ?? '',
            'theme_color' => $dbConfig['theme_color'] ?? '#0065F3',
            'domain_intro' => $dbConfig['domain_intro'] ?? '',
            'domain_price' => $dbConfig['domain_price'] ?? '',
            'logo_url' => '',
        ];
    }

    /**
     * 规范化域名（去除端口号、www前缀，转小写）
     */
    public static function normalizeDomain($domain)
    {
        if (empty($domain)) return $domain;
        $domain = preg_replace('/:\d+$/', '', $domain);
        $domain = preg_replace('/^www\./i', '', $domain);
        return strtolower($domain);
    }

    /**
     * 检测当前访问的域名
     * @return string
     */
    private function detectCurrentDomain()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        return self::normalizeDomain($host);
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
     * 获取站点设置
     * @return array 站点设置数组
     */
    private function getSiteSettings()
    {
        $settingsFile = __DIR__ . '/../data/site_settings.json';
        return $this->readJsonFile($settingsFile);
    }

    /**
     * 获取配置项
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($key, $default = null)
    {
        // 如果是 site_name，从站点设置中读取
        if ($key === 'site_name') {
            $siteSettings = $this->getSiteSettings();
            return $siteSettings['site_name'] ?? $default;
        }
        return isset($this->domainConfig[$key]) ? $this->domainConfig[$key] : $default;
    }

    /**
     * 获取所有配置
     * @return array
     */
    public function getAll()
    {
        return $this->domainConfig;
    }

    /**
     * 获取当前域名
     * @return string
     */
    public function getCurrentDomain()
    {
        return $this->currentDomain;
    }

    /**
     * 检查是否为已知域名
     * @return bool
     */
    public function isConfiguredDomain()
    {
        return $this->useDatabase && !empty($this->domainConfig);
    }
}
