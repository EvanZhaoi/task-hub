# 05-Model设计

## 1. 本章节目标

本章说明 TaskHub 8 张核心表如何映射为 Laravel Eloquent Model。本阶段只做映射，不写业务逻辑。

## 2. 涉及文件

| Model | 表 |
|---|---|
| `Task` | `task` |
| `Bid` | `bid` |
| `BidMember` | `bid_member` |
| `TaskAssignee` | `task_assignee` |
| `TaskDelivery` | `task_delivery` |
| `AttachmentRef` | `attachment_ref` |
| `TaskChangeRequest` | `task_change_request` |
| `TaskEvent` | `task_event` |

## 3. 实现步骤

1. 每个 Model 设置 `$table`。
2. 雪花 ID 主键设置 `$incrementing = false`。
3. 使用 `$fillable` 列出允许批量赋值字段。
4. 使用 `casts()` 映射 JSON、decimal、date、datetime。
5. 根据 schema 建立 `belongsTo`、`hasMany`、`morphMany`、`morphTo` 关系。
6. 不创建 BaseModel，不创建 Repository。

## 4. 核心代码讲解

Eloquent Model 与 JPA Entity 不完全一样。JPA Entity 更偏数据结构，Repository 负责查询；Eloquent 是 Active Record，Model 自身就能查询：

```php
Task::query()->where('status', 'OPEN')->get();
```

这就是本项目不额外创建 Repository 的主要原因。

`casts()` 类似 Java DTO/Entity 中的类型转换声明。例如：

```php
'payment_account_snapshot' => 'array',
'budget' => 'decimal:2',
'expected_delivery' => 'date:Y-m-d',
```

## 5. 设计原因

Model 只做映射的原因：

- Phase 1 不开发业务功能。
- 业务事务后续应放在 Service 层。
- Model 保持轻量，便于对照 `database/schema.sql` 检查。

多态附件使用 Laravel morph map，将 schema 中的 `TASK/BID/DELIVERY/CHANGE_REQUEST` 映射到对应 Model class，避免把数据库枚举改成 PHP 类名。

## 6. 注意事项

- `bid_member`、`task_assignee`、`attachment_ref`、`task_event` 没有 `updated_at`，Model 中设置 `UPDATED_AT = null`。
- `user_id` 字段名保留，但含义是人员工号，不是本地用户表 ID。
- `AttachmentRef.owner_id` 是多态关联，不建立数据库外键。
- 不要在 Model 中写选标、投标、审批等业务流程。

## 7. 本阶段完成效果

完成后可以通过 Model 表名和关系进行基础验证：

```bash
php artisan test
```

当前测试会验证 Inertia 壳和 `Task` Model 的表名映射。

## 8. 下一步

下一阶段建议先补齐 SSO 中间件和当前用户异常处理，再开始第一个业务模块。不要直接进入发布任务功能。
