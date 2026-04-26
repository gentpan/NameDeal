# NameDeal

![NameDeal Version](https://img.shields.io/badge/version-v1.5.0-0A66C2?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-enabled-003B57?style=for-the-badge&logo=sqlite&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-16A34A?style=for-the-badge)
![Status](https://img.shields.io/badge/status-production_ready-111827?style=for-the-badge)

NameDeal 是一个轻量、易部署的 PHP 域名停放与询盘管理系统。它适合个人域名投资者、域名经纪人和小型域名销售站点，用一个程序管理多个域名的展示页、询盘表单、访问统计、邮件通知和后台配置。

当前版本：`v1.5.0`  
最后更新：`2026-04-26`

## 核心特性

- 多域名绑定：一个站点程序可同时服务多个域名，根据访问域名展示对应销售页。
- 域名停放页：展示域名标题、介绍、价格、询盘表单、价值卡片和页脚链接。
- 后台管理：支持域名管理、访问统计、邮件设置、站点设置、价值卡片和页脚设置。
- AJAX 分页：域名管理列表支持当前页面内分页加载，不改变浏览器地址。
- 邮件通知：支持 SMTP、Sendflare API、Resend API，不依赖 PHP mail。
- 验证码询盘：表单提交前可通过邮箱验证码降低垃圾询盘。
- WHOIS 查询：后台通过 `https://api.who.ga/{domain}` 获取 WHOIS 信息。
- 主题模式：前后台支持浅色、深色、跟随系统。
- 图标配置：价值卡片和页脚链接支持 Font Awesome 类名，也支持安全 SVG。

## 技术栈

- PHP `8.2+`
- Nginx / Apache
- SQLite / PDO SQLite
- 原生 PHP、CSS、JavaScript
- Font Awesome

## 目录结构

```text
.
├── admin.php                  # 后台入口
├── index.php                  # 前台入口
├── api/                       # AJAX/API 接口
├── assets/                    # CSS、JavaScript、图标等静态资源
├── core/                      # 核心业务类
├── data/                      # 本地配置与 SQLite 数据库
├── templates/                 # 前台页面模板
└── nginx.conf.example
```

## 环境要求

生产环境建议：

- PHP `8.2` 或更高版本
- 启用 `curl`
- 启用 `pdo_sqlite`
- 启用 `mbstring`
- Nginx、Apache 或宝塔站点环境
- `data/` 目录需要 PHP 可写

## 直接上传部署

1. 上传项目文件到站点根目录。
2. 确认 PHP 版本为 `8.2+`，并启用 `curl`、`pdo_sqlite`。
3. 创建配置文件：

```bash
cp data/site_settings.example.json data/site_settings.json
cp data/email_settings.example.json data/email_settings.json
```

4. 设置 `data/` 目录可写。
5. 配置 Web 服务器伪静态。
6. 访问后台完成域名、邮件和站点设置。

宝塔部署建议：

- 网站目录指向项目根目录。
- PHP 版本选择 `8.2` 或更高。
- 在 PHP 扩展里启用 `curl`、`pdo_sqlite`、`sqlite3`。
- 在宝塔站点设置里添加需要绑定的多个域名。
- 在伪静态中使用 Nginx 或 Apache 规则。
- 使用宝塔 SSL 面板为多个域名申请或绑定证书。

## Nginx 配置

仓库提供了 `nginx.conf.example`。核心规则：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location = /admin {
    rewrite ^ /admin.php?$query_string last;
}

location ~ ^/admin/(domains|stats|email|site|value|footer)/?$ {
    rewrite ^/admin/([^/]+)/?$ /admin.php?section=$1 last;
}

location ^~ /data/ {
    deny all;
}

location ^~ /core/ {
    deny all;
}
```

宝塔部署时，建议把多个销售域名都绑定到同一个站点根目录，并统一使用同一套伪静态配置。SSL 可以使用宝塔的多域名证书、DNS API 自动续签，或在反向代理/CDN 层统一处理。

## Apache 配置

如果使用 Apache，请启用：

- `mod_rewrite`
- `AllowOverride All`

并确保 `.htaccess` 生效。生产环境必须禁止 Web 直接访问 `data/`、`core/` 等目录。

## 配置说明

站点配置：`data/site_settings.json`

- `site_name`：站点名称
- `site_description`：站点描述
- `admin_password_hash`：后台密码哈希
- `domain_value`：首页价值卡片配置
- `footer_links`：页脚链接配置
- `footer_analytics_code`：页脚统计代码

邮件配置：`data/email_settings.json`

- `email_provider`：`smtp`、`sendflare`、`resend`
- `smtp_*`：SMTP 连接信息
- `sendflare_api_key`：Sendflare API Key
- `resend_api_key`：Resend API Key
- `from_email`：发件邮箱
- `default_to_email`：默认收件邮箱

不要把真实的 `site_settings.json`、`email_settings.json`、`*.db` 文件提交到公开仓库。

## 后台入口

后台地址：

```text
/admin
```

首次部署请使用后台设置页修改默认密码。密码会使用 `password_hash()` 保存为哈希值。

## 邮件服务

NameDeal v1.5.0 支持三种发送方式：

- SMTP：适合自有企业邮箱、Mailgun SMTP、Amazon SES SMTP 等。
- Sendflare API：适合使用 Sendflare HTTP API 发送。
- Resend API：适合使用 Resend HTTP API 发送。

系统不使用 PHP mail，以避免服务器本地邮件环境不可控的问题。

## WHOIS 查询

后台 WHOIS 查询通过远程接口获取：

```text
https://api.who.ga/{domain}
```

项目本身不维护本地 WHOIS 解析逻辑。

## 安全建议

- 部署后立即修改后台密码。
- 不要公开提交 `data/*.json` 中的真实密钥。
- 不要公开提交 `data/*.db` 数据库文件。
- Web 服务器必须禁止访问 `data/` 和 `core/`。
- 邮件 API Key 建议定期轮换。
- 多域名生产部署建议统一使用 HTTPS，并开启 HSTS。

## 版本记录

### v1.5.0 · 2026-04-26

- 项目运行环境标注为 PHP `8.2+`，部署方式调整为直接上传到普通 PHP 站点。
- 新增 Sendflare API、Resend API 邮件发送支持，移除 PHP mail 发送方式。
- WHOIS 查询改为通过 `api.who.ga` 获取。
- 后台界面重构为全宽页头页脚、固定内容宽度和直角风格。
- 优化后台滚动条、WHOIS 弹窗、主题菜单和页脚链接视觉状态。
- 域名管理分页改为 AJAX 当前页加载。
- 首页价值卡片和页脚链接支持后台配置 Font Awesome 图标。

### v1.1.0 · 2026-02-13

- 修复深色模式下邮件模板展示问题。
- 新增页脚图标固定尺寸规则，统一 SVG 与 Font Awesome 渲染尺寸。
- 补充 Nginx/Apache 静态化与伪静态配置说明。

### v1.0.0 · 2026-02-12

- 完成开源基线整理。
- 提供多域名停放、询盘表单、邮箱验证码与后台管理基础功能。
- 加固后台密码哈希和 CSRF 防护。

## License

MIT License
