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
6. [07-Eloquent-Model设计](./07-Eloquent-Model设计.md)
7. [08-最小业务闭环](./08-最小业务闭环.md)
8. [09-SSO隐式模式接入](./09-SSO隐式模式接入.md)
9. [10-任务大厅与业务布局](./10-任务大厅与业务布局.md)

## 两种学习路径

如果你是“拉取当前 TaskHub 仓库”学习：

```text
00 → 02 → 03 → 04 → 07 → 08
```

当前仓库已经包含 `database/schema.sql` 和 8 个 Eloquent Model，所以你在第 08 章可以直接看到 `app/Models/Task.php`。

如果你是“从空目录跟着 01 章手动创建项目”学习：

```text
00 → 01 → 03 → 04 → 07 → 08
```

只完成 01 和 03 后，看不到 `app/Models/Task.php` 是正常的。原因是：

- 01 只创建 Laravel + React + Inertia 项目骨架。
- 03 只解释项目结构和请求流程。
- `database/schema.sql` 在 04 章讲解。
- `app/Models/Task.php` 等 Model 在 07 章讲解。
- 08 章才第一次使用 `Task` Model 做只读业务闭环。

因此，如果你从零跟做，进入 08 章前请先确认：

```bash
test -f database/schema.sql && echo "schema exists"
test -f app/Models/Task.php && echo "Task model exists"
```

Windows PowerShell：

```powershell
Test-Path database/schema.sql
Test-Path app/Models/Task.php
```

## 当前阶段边界

Phase 1 只完成基础环境、认证骨架、数据库映射和开发文档。

已经完成：

- Laravel 13 单体项目骨架
- React + TypeScript + Inertia.js
- Vite 开发和生产构建
- MySQL 8 配置
- Pest 测试入口
- SSO 接入骨架
- SSO 隐式模式登录回调与本地角色控制
- 9 个 Eloquent Model
- 只读任务列表最小业务闭环
- 正式任务大厅布局、筛选和分页

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
- 文档中的可复制代码块必须增加必要中文注释，重点解释“为什么这样写”；不要给每一行添加无意义注释。
