# TaskHub

TaskHub 是公司内部任务撮合平台，目标是用市场化方式让任务发布者和有空的开发者完成匹配。

当前仓库处于 **Phase 1：项目初始化与基础架构**。本阶段只完成 Laravel 单体架构、React/Inertia 前端壳、数据库映射、SSO 骨架和开发文档，不包含任务发布、投标、交付等业务功能。

## 技术栈

- PHP 8.5.8
- Laravel 13
- React 19
- TypeScript
- Inertia.js
- Vite
- Tailwind CSS 4 + shadcn/ui 风格基础组件
- MySQL 8
- Pest

架构方式：

```text
Laravel Monolith + React(Inertia)
```

不是传统 Blade 页面，也不是完全前后端分离。

## 快速启动

```bash
git clone <repository-url> task-hub
cd task-hub
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan config:clear
composer run dev
```

Windows PowerShell 复制 `.env`：

```powershell
Copy-Item .env.example .env
```

访问：

```text
http://127.0.0.1:8000
```

## 数据库初始化

数据库结构以 [database/schema.sql](./database/schema.sql) 为当前唯一标准。

```bash
mysql -uroot -p < database/schema.sql
php artisan config:clear
php artisan db:show
```

不要运行会创建无关表的默认 Laravel migration。当前项目不创建本地 `users` 表。

详细说明见：

- [04-数据库初始化与配置](./docs/development/04-数据库初始化与配置.md)

## 开发文档

从这里开始阅读：

- [开发文档 README](./docs/development/README.md)
- [00-开发环境准备](./docs/development/00-开发环境准备.md)
- [01-从零创建项目](./docs/development/01-从零创建项目.md)
- [02-拉取并运行现有项目](./docs/development/02-拉取并运行现有项目.md)
- [11-shadcn-ui接入](./docs/development/11-shadcn-ui接入.md)

## SSO 当前状态

当前只保留真实 SSO 接入骨架：

- `app/Integrations/Sso/SsoClient.php`
- `app/Integrations/Sso/SsoUser.php`
- `app/Services/CurrentUserService.php`
- `app/Http/Middleware/EnsureSsoAuthenticated.php`

SSO 真实地址需要按公司协议填写。`SSO_LOGIN_URL` 使用浏览器可跳转的完整 URL；`SSO_USERINFO_PATH` / `SSO_VALIDATE_PATH` 只填写接口 path，由代码与 `SSO_BASE_URL` 组合。获取当前登录人信息按公司推荐方式使用 POST JSON，提交 `clientId`、`secret`、`accessToken`。项目不使用 Mock 登录、不硬编码工号、不创建本地用户表。

详细说明见：

- [09-SSO隐式模式接入](./docs/development/09-SSO隐式模式接入.md)

## 已完成

- Laravel 13 项目初始化
- React + TypeScript + Inertia 初始化
- Vite 开发和生产构建
- MySQL 8 配置
- 删除默认本地用户体系 migration
- 8 个 Eloquent Model 映射
- SSO 接入骨架
- Pest 基础测试
- shadcn/ui 风格基础组件
- 面向 Java 开发者的开发文档

## 尚未开发

- 发布任务
- 投标
- 选标
- 交付
- 任务变更
- 通知
- 权限矩阵
- Dashboard / 甘特图
- 真实 SSO 协议联调

## 验证命令

```bash
composer validate --strict
npm run typecheck
npm run build
php artisan test
```

## 安全声明

本仓库为公开仓库。禁止提交：

- `.env`
- 真实 SSO 地址、密钥、Token
- 真实用户信息、工号与部门数据
- 真实付款账号
- 真实任务内容或附件

所有公司内部数据必须脱敏后再进入文档或测试数据。
