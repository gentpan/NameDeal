# NameDeal

一个轻量的 PHP 域名停放与询盘管理系统，支持：

- 多域名配置与停放展示
- 后台管理（域名、统计、邮件、站点设置）
- 邮件验证码 + 询盘表单
- SQLite 访问统计

## 环境要求

- PHP 8.1+
- PDO SQLite 扩展
- Web 服务器（Apache/Nginx）

## 快速开始

1. 复制示例配置文件：
   - `data/site_settings.example.json` -> `data/site_settings.json`
   - `data/email_settings.example.json` -> `data/email_settings.json`
2. 修改配置中的密码与 SMTP 参数。
3. 启动服务并访问：
   - 前台：`/index.php`
   - 后台：`/admin.php`

## Nginx / Apache 静态化配置

本项目支持“伪静态”路由，目标是：

- `/admin`、`/admin/footer` 这类路径可直接访问
- 域名路径（如 `/example.com`）重写到 `index.php?domain=...`
- 静态资源（`/assets/*`）走缓存，提高加载速度

### Nginx（推荐）

仓库已提供完整示例：`nginx.conf.example`。  
重点规则如下（可直接参考）：

- `location / { try_files $uri $uri/ /index.php?$query_string; }`
- `location = /admin { rewrite ^ /admin.php?$query_string last; }`
- `location ~ ^/admin/(domains|stats|email|site|footer)/?$ { rewrite ... }`
- `location ^~ /assets/ { expires 7d; add_header Cache-Control "public, max-age=604800"; }`
- `location ^~ /data/ { deny all; }`、`location ^~ /core/ { deny all; }`

建议做法：

1. 复制 `nginx.conf.example` 到站点配置目录。
2. 修改 `server_name`、`root`、`fastcgi_pass`。
3. 执行 `nginx -t` 后重载 Nginx。

### Apache（.htaccess）

项目根目录已包含 `.htaccess`，核心逻辑：

- 非真实文件/目录时，按规则重写到 `index.php` 或对应 `*.php`
- 支持域名路径重写（`/domain.tld` -> `index.php?domain=domain.tld`）
- 兼容历史入口（如 `search.php`、`api.php`）

启用要求：

1. 开启 `mod_rewrite`：`a2enmod rewrite`
2. 站点目录允许覆盖：`AllowOverride All`
3. 重载 Apache：`systemctl reload apache2`（或对应服务名）

### 静态缓存建议

- CSS/JS/图片：`Cache-Control: public, max-age=604800`（7 天）
- 频繁变更资源建议带版本号（如 `style.css?v=20260213`）避免缓存未更新
- `data/`、`core/` 必须禁止 Web 直接访问

## 安全说明

- 后台密码已使用 `password_hash()` 存储。
- 后台 POST 操作启用了 CSRF 防护。
- `data/` 目录通过 `.htaccess` 禁止 Web 访问。
- 开源前请务必轮换所有现有密码、SMTP 凭据与邮箱账号。

## 许可证

建议使用 MIT License（可按你的开源策略调整）。
