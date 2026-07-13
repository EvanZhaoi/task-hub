# TaskHub 开发文档

本目录用于记录 TaskHub Phase 1 的基础架构搭建方式。文档面向两类读者：

1. 从零学习如何创建 Laravel + React + Inertia 项目的开发者。
2. 已经拉取 TaskHub 仓库，希望本地运行和继续开发的团队成员。

## 阅读顺序

1. [00-开发环境准备](./00-开发环境准备.md)
2. [01-从零创建项目](./01-从零创建项目.md)
3. [02-拉取并运行现有项目](./02-拉取并运行现有项目.md)
4. [03-项目结构](./03-项目结构.md)
5. [04-数据库初始化与配置](./04-数据库初始化与配置.md)
6. [05-Inertia与React初始化](./05-Inertia与React初始化.md)
7. [06-SSO认证流程](./06-SSO认证流程.md)
8. [07-Eloquent-Model设计](./07-Eloquent-Model设计.md)

## 当前阶段边界

Phase 1 只完成基础环境、认证骨架、数据库映射和开发文档。

已经完成：

- Laravel 13 单体项目骨架
- React + TypeScript + Inertia.js
- Vite 开发和生产构建
- MySQL 8 配置
- Pest 测试入口
- SSO 接入骨架
- 8 个 Eloquent Model

尚未开发：

- 发布任务
- 投标
- 选标
- 交付
- 任务变更
- 通知
- 权限矩阵
- Dashboard / 甘特图

## 操作约定

- 所有命令默认在项目根目录执行，除非文档明确写出其他目录。
- 不提交 `.env`。
- 不执行会创建无关表的 Laravel 默认 migration。
- `database/schema.sql` 是当前数据库结构标准。
- 业务开发前先阅读对应章节并确认基础命令能通过。
