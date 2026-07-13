# 07-Eloquent-Model设计

## 1. 本章目标

以 `Task` Model 为完整例子，说明如何把 `database/schema.sql` 中的表映射为 Eloquent Model。

## 2. 前置条件

- 已执行 `database/schema.sql`。
- 已理解 TaskHub 不使用本地 users 表。

## 3. 本章最终效果

可以用 Tinker 执行：

```php
App\Models\Task::query()->count();
```

## 4. 实际执行命令

从零创建 Model：

```bash
php artisan make:model Task
```

当前仓库已创建 8 个 Model，无需重复执行。

验证：

```bash
php artisan tinker
```

## 5. 命令执行目录

项目根目录。

## 6. 命令作用

- `make:model Task`：生成 `app/Models/Task.php`。
- `tinker`：进入 Laravel 交互环境，验证 Model 查询。

## 7. 预期输出或生成文件

生成：

```text
app/Models/Task.php
```

Tinker 验证空库：

```text
= 0
```

## 8. 逐文件修改过程

### 8.1 默认生成内容

```php
class Task extends Model
{
    //
}
```

### 8.2 设置表名

```php
protected $table = 'task';
```

因为数据库表名是单数 `task`，不是 Laravel 默认推断的 `tasks`。

### 8.3 设置雪花 ID

```php
public $incrementing = false;
protected $keyType = 'int';
```

数据库主键由业务生成，不是 MySQL 自增。

### 8.4 配置 `$fillable`

```php
protected $fillable = [
    'id',
    'title',
    'description',
    'payment_account_id',
    'budget',
    'status',
];
```

实际项目中已列出完整字段。

### 8.5 配置 `casts()`

```php
protected function casts(): array
{
    return [
        'payment_account_snapshot' => 'array',
        'budget' => 'decimal:2',
        'expected_delivery' => 'date:Y-m-d',
        'bidding_deadline' => 'datetime',
    ];
}
```

### 8.6 配置关系

```php
public function bids(): HasMany
{
    return $this->hasMany(Bid::class, 'task_id');
}
```

## 9. 修改前后的关键代码

修改前：

```php
class Task extends Model
{
    //
}
```

修改后：

```php
class Task extends Model
{
    protected $table = 'task';
    public $incrementing = false;
    protected $keyType = 'int';
}
```

## 10. 核心原理解释

Eloquent 是 Active Record。Model 不只是字段结构，也能直接发起查询：

```php
Task::query()->where('status', 'OPEN')->get();
```

因此 Phase 1 不增加 Repository。

## 11. 与 Spring Boot 的概念对照

| Eloquent | Spring Boot / JPA |
|---|---|
| Model | Entity + 部分 Repository 能力 |
| `$table` | `@Table(name = "...")` |
| `casts()` | 字段类型转换 |
| `hasMany` / `belongsTo` | `@OneToMany` / `@ManyToOne` |
| Tinker | 临时 REPL 验证 |

## 12. 验证步骤

```bash
php artisan tinker
```

Tinker：

```php
App\Models\Task::query()->count();
$task = new App\Models\Task();
$task->getTable();
```

验证关系：

```php
(new App\Models\Task())->bids();
```

## 13. 常见错误与解决方法

| 错误 | 处理 |
|---|---|
| 查询 `tasks` 表不存在 | 设置 `$table = 'task'` |
| 插入时主键异常 | 设置 `$incrementing = false` |
| JSON 字段返回字符串 | 在 `casts()` 中配置 `array` |
| 金额精度不对 | 使用 `decimal:2` |
| 工号误当本地用户 ID | 不建立 user 外键 |

## 14. 操作检查清单

- [ ] Model 表名与 schema 一致
- [ ] 雪花 ID 配置正确
- [ ] JSON/date/decimal casts 正确
- [ ] 关系只表达结构，不写业务流程
- [ ] 不创建 Repository

## 15. 本章对应的实际项目文件

| Model | 表 | 说明 |
|---|---|---|
| `Task` | `task` | 任务主表 |
| `Bid` | `bid` | 投标 |
| `BidMember` | `bid_member` | 投标成员 |
| `TaskAssignee` | `task_assignee` | 执行成员 |
| `TaskDelivery` | `task_delivery` | 交付 |
| `AttachmentRef` | `attachment_ref` | 附件引用 |
| `TaskChangeRequest` | `task_change_request` | 变更请求 |
| `TaskEvent` | `task_event` | 任务事件 |

## 16. 下一章入口

基础架构文档到此结束。下一阶段建议补齐 SSO Middleware 的真实协议接入，不要直接进入任务业务功能。
