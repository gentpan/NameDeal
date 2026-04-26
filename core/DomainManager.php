<?php

/**
 * 域名管理类
 * 负责管理域名配置数据的增删改查
 */

class DomainManager
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
            $dbPath = __DIR__ . '/../data/domains.db';
        }

        $this->dbPath = $dbPath;

        // 确保数据目录存在
        $dataDir = dirname($this->dbPath);
        if (!file_exists($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // 初始化数据库连接
        $this->initDatabase();

        // 初始化默认域名
        $this->initDefaultDomains();
    }

    /**
     * 初始化数据库
     */
    private function initDatabase()
    {
        try {
            $this->db = new PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 创建域名配置表
            $this->db->exec("CREATE TABLE IF NOT EXISTS domains (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                domain TEXT UNIQUE NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                theme_color TEXT DEFAULT '#0065F3',
                domain_intro TEXT,
                domain_price TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT (datetime('now', 'localtime')),
                updated_at TEXT DEFAULT (datetime('now', 'localtime'))
            )");
            
            // 迁移现有数据：如果存在旧字段，添加新字段
            try {
                $this->db->exec("ALTER TABLE domains ADD COLUMN domain_intro TEXT");
            } catch (PDOException $e) {
                // 字段已存在，忽略错误
            }
            
            try {
                $this->db->exec("ALTER TABLE domains ADD COLUMN domain_price TEXT");
            } catch (PDOException $e) {
                // 字段已存在，忽略错误
            }
        } catch (PDOException $e) {
            error_log("数据库初始化失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 初始化默认域名配置
     */
    private function initDefaultDomains()
    {
        // 检查是否已有域名配置
        $stmt = $this->db->query("SELECT COUNT(*) FROM domains");
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            return; // 已有配置，不需要初始化
        }

        // 数据现在完全由数据库管理，不再从配置文件迁移
    }

    /**
     * 添加域名配置
     * @param array $data 域名配置数据
     * @return int|false 新增记录的ID，失败返回false
     */
    public function addDomain($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO domains (domain, title, description, theme_color, domain_intro, domain_price)
                VALUES (:domain, :title, :description, :theme_color, :domain_intro, :domain_price)
            ");

            $stmt->execute([
                ':domain' => $data['domain'],
                ':title' => $data['title'],
                ':description' => $data['description'] ?? '',
                ':theme_color' => $data['theme_color'] ?? '#0065F3',
                ':domain_intro' => $data['domain_intro'] ?? '',
                ':domain_price' => $data['domain_price'] ?? ''
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("添加域名失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新域名配置
     * @param int $id 域名ID
     * @param array $data 更新的数据
     * @return bool 成功返回true，失败返回false
     */
    public function updateDomain($id, $data)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE domains
                SET title = :title,
                    description = :description,
                    theme_color = :theme_color,
                    domain_intro = :domain_intro,
                    domain_price = :domain_price,
                    is_active = :is_active,
                    updated_at = datetime('now', 'localtime')
                WHERE id = :id
            ");

            return $stmt->execute([
                ':id' => $id,
                ':title' => $data['title'],
                ':description' => $data['description'] ?? '',
                ':theme_color' => $data['theme_color'] ?? '#0065F3',
                ':domain_intro' => $data['domain_intro'] ?? '',
                ':domain_price' => $data['domain_price'] ?? '',
                ':is_active' => $data['is_active'] ?? 1
            ]);
        } catch (PDOException $e) {
            error_log("更新域名失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除域名配置
     * @param int $id 域名ID
     * @return bool 成功返回true，失败返回false
     */
    public function deleteDomain($id)
    {
        try {
            // 先获取域名信息，以便删除对应的统计数据
            $stmt = $this->db->prepare("SELECT domain FROM domains WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $domain = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$domain) {
                // 域名不存在
                return false;
            }
            
            $domainName = $domain['domain'];
            
            // 删除统计数据
            require_once __DIR__ . '/StatsTracker.php';
            $statsTracker = new StatsTracker();
            $statsTracker->deleteStats($domainName);
            
            // 删除域名配置
            $stmt = $this->db->prepare("DELETE FROM domains WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("删除域名失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据域名获取配置
     * @param string $domain 域名
     * @return array|false 配置数组，失败返回false
     */
    public function getDomainConfig($domain)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM domains WHERE domain = :domain AND is_active = 1");
            $stmt->execute([':domain' => $domain]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("获取域名配置失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取所有域名配置
     * @param bool $activeOnly 是否只获取激活的域名
     * @return array 域名配置数组
     */
    public function getAllDomains($activeOnly = false)
    {
        try {
            $sql = "SELECT * FROM domains";
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY created_at DESC";

            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("获取域名列表失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 根据ID获取域名配置
     * @param int $id 域名ID
     * @return array|false 配置数组，失败返回false
     */
    public function getDomainById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM domains WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("获取域名配置失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 切换域名激活状态
     * @param int $id 域名ID
     * @return bool 成功返回true，失败返回false
     */
    public function toggleActive($id)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE domains 
                SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END,
                    updated_at = datetime('now', 'localtime')
                WHERE id = :id
            ");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("切换域名状态失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取域名总数
     * @return int 域名总数
     */
    public function getDomainsCount()
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM domains WHERE is_active = 1");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("获取域名总数失败: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 检查域名是否存在
     * @param string $domain 域名
     * @param int $excludeId 排除的ID（用于编辑时检查）
     * @return bool 存在返回true，不存在返回false
     */
    public function domainExists($domain, $excludeId = null)
    {
        try {
            $sql = "SELECT COUNT(*) FROM domains WHERE domain = :domain";
            $params = [':domain' => $domain];

            if ($excludeId !== null) {
                $sql .= " AND id != :id";
                $params[':id'] = $excludeId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("检查域名存在失败: " . $e->getMessage());
            return false;
        }
    }
}
