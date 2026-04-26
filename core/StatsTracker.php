<?php

/**
 * 访问统计追踪类
 * 负责记录每个域名的访问日志
 */

class StatsTracker
{

    private $db;
    private $dbPath;

    /**
     * 构造函数
     * @param string $dbPath 数据库文件路径
     */
    public function __construct($dbPath = null)
    {
        if ($dbPath === null) {
            $dbPath = __DIR__ . '/../data/stats.db';
        }

        $this->dbPath = $dbPath;

        // 确保数据目录存在
        $dataDir = dirname($this->dbPath);
        if (!file_exists($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // 初始化数据库连接
        $this->initDatabase();
    }

    /**
     * 初始化数据库
     */
    private function initDatabase()
    {
        try {
            $this->db = new PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 创建日志表
            $this->db->exec("CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                domain TEXT NOT NULL,
                uri TEXT,
                ip TEXT,
                user_agent TEXT,
                referer TEXT,
                time TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // 创建索引以提升查询性能
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_domain ON logs(domain)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_time ON logs(time)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON logs(created_at)");
        } catch (PDOException $e) {
            error_log("Stats database initialization failed: " . $e->getMessage());
        }
    }

    /**
     * 规范化域名（统一去除 www 前缀，使带 www 和不带 www 的域名被视为同一个）
     * @param string $domain 原始域名
     * @return string 规范化后的域名
     */
    private function normalizeDomain($domain)
    {
        require_once __DIR__ . '/DomainConfig.php';
        return DomainConfig::normalizeDomain($domain);
    }

    /**
     * 记录访问日志
     * @return bool
     */
    public function track()
    {
        try {
            // 启动 session（如果还没有启动）
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            // 规范化域名（去除 www 前缀，统一处理）
            $domain = $this->normalizeDomain($domain);

            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $ip = $this->getClientIP();
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $time = date('Y-m-d H:i:s');

            // 过滤掉静态资源请求和爬虫请求（可选）
            if ($this->shouldSkipTracking($uri, $userAgent)) {
                return false;
            }

            // 生成请求的唯一标识（用于防止重复统计）
            $requestKey = md5($domain . $uri . $ip . $userAgent . date('Y-m-d H:i'));

            // 检查这个请求在本次会话中是否已经统计过
            $sessionKey = 'stats_tracked_' . $requestKey;
            if (isset($_SESSION[$sessionKey])) {
                // 在同一个会话中（同一分钟内），相同请求只统计一次
                return false;
            }

            // 额外检查：同一IP在短时间内是否有相同的请求（防止并发请求和重复刷新）
            $recentTime = date('Y-m-d H:i:s', strtotime('-3 seconds'));
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM logs 
                WHERE domain = ? 
                  AND uri = ? 
                  AND ip = ? 
                  AND created_at >= ?
            ");
            $stmt->execute([$domain, $uri, $ip, $recentTime]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['count'] > 0) {
                // 3秒内已经有相同请求，不重复统计
                return false;
            }

            // 记录访问
            $stmt = $this->db->prepare("INSERT INTO logs (domain, uri, ip, user_agent, referer, time) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$domain, $uri, $ip, $userAgent, $referer, $time]);

            // 标记本次会话中已统计（有效期1分钟）
            $_SESSION[$sessionKey] = true;
            $_SESSION[$sessionKey . '_time'] = time();

            // 偶尔清理过期的 session 标记（10%的概率执行，减少性能开销）
            if (rand(1, 10) === 1) {
                $this->cleanupSessionMarks();
            }

            return true;
        } catch (PDOException $e) {
            error_log("Stats tracking failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 清理过期的 session 统计标记
     */
    private function cleanupSessionMarks()
    {
        $expireTime = time() - 300; // 5分钟前的标记过期

        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'stats_tracked_') === 0 && isset($_SESSION[$key . '_time'])) {
                if ($_SESSION[$key . '_time'] < $expireTime) {
                    unset($_SESSION[$key]);
                    unset($_SESSION[$key . '_time']);
                }
            }
        }
    }

    /**
     * 获取客户端真实IP
     * @return string
     */
    private function getClientIP()
    {
        $ip = '';

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP']) && $_SERVER['HTTP_X_REAL_IP']) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // 如果有多个IP，取第一个
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }

        return $ip;
    }

    /**
     * 判断是否应该跳过统计
     * @param string $uri
     * @param string $userAgent
     * @return bool
     */
    private function shouldSkipTracking($uri, $userAgent)
    {
        // 跳过后台管理页面
        if (strpos($uri, '/admin.php') !== false || strpos($uri, 'admin.php') !== false) {
            return true;
        }

        // 跳过API接口（如果有）
        if (strpos($uri, '/api/') !== false) {
            return true;
        }

        // 跳过静态资源
        $staticExtensions = ['.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf', '.eot'];
        foreach ($staticExtensions as $ext) {
            if (substr($uri, -strlen($ext)) === $ext) {
                return true;
            }
        }

        // 跳过常见爬虫（可选，如果想统计爬虫访问可以注释掉这部分）
        $botPatterns = ['bot', 'crawler', 'spider', 'scraper'];
        $userAgentLower = strtolower($userAgent);
        foreach ($botPatterns as $pattern) {
            if (strpos($userAgentLower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取指定域名的访问统计
     * @param string $domain
     * @param int $days 统计最近几天，默认30天
     * @return array
     */
    public function getStats($domain, $days = 30)
    {
        try {
            // 规范化域名（去除 www 前缀，统一处理）
            $domain = $this->normalizeDomain($domain);

            $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            // 总访问量（排除后台和API访问）
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM logs 
                WHERE domain = ? 
                  AND created_at >= ?
                  AND uri NOT LIKE '%admin.php%'
                  AND uri NOT LIKE '%/api/%'
            ");
            $stmt->execute([$domain, $date]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 独立IP数（排除后台和API访问）
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT ip) as unique_ips 
                FROM logs 
                WHERE domain = ? 
                  AND created_at >= ?
                  AND uri NOT LIKE '%admin.php%'
                  AND uri NOT LIKE '%/api/%'
            ");
            $stmt->execute([$domain, $date]);
            $uniqueIps = $stmt->fetch(PDO::FETCH_ASSOC)['unique_ips'];

            // 今日访问量（排除后台和API访问）
            $today = date('Y-m-d');
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as today 
                FROM logs 
                WHERE domain = ? 
                  AND created_at >= ?
                  AND uri NOT LIKE '%admin.php%'
                  AND uri NOT LIKE '%/api/%'
            ");
            $stmt->execute([$domain, $today . ' 00:00:00']);
            $todayCount = $stmt->fetch(PDO::FETCH_ASSOC)['today'];

            return [
                'domain' => $domain,
                'total_visits' => (int)$total,
                'unique_ips' => (int)$uniqueIps,
                'today_visits' => (int)$todayCount,
                'period_days' => $days
            ];
        } catch (PDOException $e) {
            error_log("Stats retrieval failed: " . $e->getMessage());
            return [
                'domain' => $domain,
                'total_visits' => 0,
                'unique_ips' => 0,
                'today_visits' => 0,
                'period_days' => $days
            ];
        }
    }

    /**
     * 获取所有域名的访问统计
     * @param int $days
     * @return array
     */
    public function getAllDomainsStats($days = 30)
    {
        try {
            $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            // 使用 created_at 字段进行筛选（数据库自动生成的时间戳）
            // 同时排除后台访问（通过URI过滤已在shouldSkipTracking中处理）
            // 由于 domain 已经规范化（去除了 www），所以直接按 domain 分组即可
            $stmt = $this->db->prepare("
                SELECT 
                    domain,
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT ip) as unique_ips,
                    MAX(created_at) as last_visit
                FROM logs 
                WHERE created_at >= ?
                  AND uri NOT LIKE '%admin.php%'
                  AND uri NOT LIKE '%/api/%'
                GROUP BY domain
                ORDER BY total_visits DESC
            ");
            $stmt->execute([$date]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 格式化最后访问时间
            foreach ($results as &$result) {
                if ($result['last_visit']) {
                    $result['last_visit'] = date('Y-m-d H:i:s', strtotime($result['last_visit']));
                } else {
                    $result['last_visit'] = '暂无访问';
                }
            }

            return $results;
        } catch (PDOException $e) {
            error_log("Stats retrieval failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 删除指定域名的所有统计数据
     * @param string $domain 域名（支持带 www 和不带 www）
     * @return bool 成功返回true，失败返回false
     */
    public function deleteStats($domain)
    {
        try {
            // 规范化域名（去除 www 前缀，统一处理）
            $domain = $this->normalizeDomain($domain);

            // 删除该域名的所有访问日志
            $stmt = $this->db->prepare("DELETE FROM logs WHERE domain = ?");
            $stmt->execute([$domain]);

            return true;
        } catch (PDOException $e) {
            error_log("删除统计数据失败: " . $e->getMessage());
            return false;
        }
    }
}
