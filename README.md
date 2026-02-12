# DomainHarbor

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

## 安全说明

- 后台密码已使用 `password_hash()` 存储。
- 后台 POST 操作启用了 CSRF 防护。
- `data/` 目录通过 `.htaccess` 禁止 Web 访问。
- 开源前请务必轮换所有现有密码、SMTP 凭据与邮箱账号。

## 许可证

建议使用 MIT License（可按你的开源策略调整）。
