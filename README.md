# NameDeal

[![Version](https://img.shields.io/badge/version-v1.1.0-0A66C2.svg?style=for-the-badge)](https://github.com/gentpan/NameDeal)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![SQLite](https://img.shields.io/badge/SQLite-Enabled-003B57.svg?style=for-the-badge&logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![License](https://img.shields.io/badge/license-MIT-16A34A.svg?style=for-the-badge)](./LICENSE)
[![Status](https://img.shields.io/badge/status-Production_Ready-111827.svg?style=for-the-badge)](https://github.com/gentpan/NameDeal)

> Version: `v1.1.0`  
> Last Updated: `2026-02-13`

## 中文简介

NameDeal 是一个轻量级 PHP 域名停放与询盘管理系统，支持：

- 多域名配置与停放展示
- 后台管理（域名、统计、邮件、站点设置）
- 邮件验证码 + 询盘表单
- SQLite 访问统计

## English Overview

NameDeal is a lightweight PHP domain parking and inquiry management system with:

- Multi-domain parking and presentation
- Admin panel (domains, stats, email, site settings)
- Email verification code + inquiry form
- SQLite-based visit statistics

## 环境要求 / Requirements

- PHP 8.1+
- PDO SQLite extension
- Web server (Apache / Nginx)

## 快速开始 / Quick Start

1. 复制示例配置 / Copy example configs:
   - `data/site_settings.example.json` -> `data/site_settings.json`
   - `data/email_settings.example.json` -> `data/email_settings.json`
2. 修改密码与 SMTP 参数 / Update admin password and SMTP settings.
3. 启动服务并访问 / Start service and open:
   - Frontend: `/index.php`
   - Admin: `/admin.php`

## Nginx / Apache 静态化配置 (Pseudo-static Routing)

目标 / Goals:

- 支持 `/admin`、`/admin/footer` 等友好路径
- 域名路径（如 `/example.com`）重写到 `index.php?domain=...`
- 静态资源（`/assets/*`）启用缓存提升加载性能

### Nginx (Recommended)

仓库提供完整示例：`nginx.conf.example`。

Core rules:

- `location / { try_files $uri $uri/ /index.php?$query_string; }`
- `location = /admin { rewrite ^ /admin.php?$query_string last; }`
- `location ~ ^/admin/(domains|stats|email|site|footer)/?$ { rewrite ... }`
- `location ^~ /assets/ { expires 7d; add_header Cache-Control "public, max-age=604800"; }`
- `location ^~ /data/ { deny all; }` and `location ^~ /core/ { deny all; }`

建议步骤 / Suggested steps:

1. 复制 `nginx.conf.example` 到站点配置。
2. 修改 `server_name`、`root`、`fastcgi_pass`。
3. 执行 `nginx -t` 后重载 Nginx。

### Apache (.htaccess)

项目根目录包含 `.htaccess`，可实现：

- 非真实文件/目录自动重写到 `index.php` 或对应 `*.php`
- 域名路径重写（`/domain.tld` -> `index.php?domain=domain.tld`）
- 兼容历史入口（如 `search.php`、`api.php`）

Enable requirements:

1. Enable `mod_rewrite`: `a2enmod rewrite`
2. Allow overrides in vhost: `AllowOverride All`
3. Reload Apache: `systemctl reload apache2`

### 缓存建议 / Cache Recommendations

- CSS/JS/images: `Cache-Control: public, max-age=604800` (7 days)
- 频繁变更资源建议加版本号（如 `style.css?v=20260213`）
- `data/` and `core/` must be blocked from direct web access

## 更新记录 / Changelog

### 2026-02-13 · v1.1.0

- 修复深色模式邮件模板显示，并同步前后台更新。
- 修复页脚图标尺寸与 SVG/Font Awesome 悬浮变色一致性。
- 升级 Font Awesome CDN 到 `7.2.0`。
- 新增 Nginx/Apache 静态化配置说明与缓存建议。

### 2026-02-12 · v1.0.0

- 完成开源基线整理：项目更名为 NameDeal，统一仓库链接。
- 加固后台安全（密码哈希、CSRF 防护等）。
- 提供域名停放、询盘表单、邮箱验证码与后台管理功能。

## 安全说明 / Security

- 后台密码使用 `password_hash()` 存储。
- 后台 POST 操作启用了 CSRF 防护。
- `data/` 目录通过 `.htaccess` 禁止 Web 访问。
- 开源前请务必轮换现有密码、SMTP 凭据与邮箱账号。

## 许可证 / License

MIT License (recommended).
