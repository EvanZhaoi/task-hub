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

这一节是本章重点。你可以把 Eloquent Model 理解成 Laravel 对数据库表的一层“对象化入口”：

```text
数据库表 task
↓
app/Models/Task.php
↓
PHP 对象 Task
↓
Controller / Tinker / 测试中使用
```

和 Java / Spring Boot 的 JPA Entity 相比，Eloquent Model 不只是字段结构，它还直接带查询能力。例如：

```php
Task::query()->where('status', 'OPEN')->get();
```

这也是为什么 TaskHub Phase 1 暂时不增加 Repository：简单查询直接用 Model 更符合 Laravel 官方习惯。

### 8.1 默认生成内容：Laravel 只给你一个空模型

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    //
}
```

执行：

```bash
php artisan make:model Task
```

Laravel 会生成 `app/Models/Task.php`。

默认生成内容非常少，因为 Laravel 假设你愿意遵循它的约定：

- Model 类名：`Task`
- 默认表名：`tasks`
- 默认主键：`id`
- 默认主键自增：是
- 默认维护时间字段：`created_at`、`updated_at`

TaskHub 有一部分设计和 Laravel 默认约定不同，所以需要手动配置。

### 8.2 设置表名：告诉 Laravel 不要去查 `tasks`

```php
// TaskHub 的表名是单数 task。
// 如果不设置，Laravel 会按约定查复数表 tasks。
protected $table = 'task';
```

作用：

- 明确 `Task` Model 对应数据库中的 `task` 表。
- 避免 Laravel 自动推断成 `tasks`。

为什么 Laravel 默认用复数表名：

- Laravel 约定一个 Model 类表示一类资源。
- `Task` 表示一个任务对象。
- 数据表通常存多条任务，所以默认推断为 `tasks`。

为什么 TaskHub 不用默认：

- 当前数据库设计已经确定表名是 `task`。
- SQL 是当前唯一标准，代码要服从 `database/schema.sql`。

如果不配置，会出现：

```text
SQLSTATE[42S02]: Base table or view not found:
Table 'taskhub.tasks' doesn't exist
```

Spring Boot 对照：

```java
@Entity
@Table(name = "task")
public class Task {
}
```

### 8.3 设置主键策略：TaskHub 不使用数据库自增

```php
// 主键不是 MySQL AUTO_INCREMENT。
public $incrementing = false;

// 主键值是整数类型。
protected $keyType = 'int';
```

作用：

- `$incrementing = false` 告诉 Laravel：插入时不要假设数据库会自动生成 ID。
- `$keyType = 'int'` 告诉 Laravel：主键值按整数处理。

Laravel 默认行为：

- 默认认为主键叫 `id`。
- 默认认为 `id` 是自增整数。
- 保存新模型后，会尝试读取数据库生成的新 ID。

TaskHub 的实际情况：

- 数据库字段是 `BIGINT UNSIGNED`。
- 注释中说明是雪花 ID。
- ID 通常由业务层或统一 ID 生成器生成。
- 数据库不负责自增。

为什么这一点重要：

- 如果你后续写入任务数据，必须先给 `id`。
- 如果忘记配置 `$incrementing = false`，Laravel 可能按自增主键逻辑处理，导致插入或保存行为不符合预期。

扩展知识：

- 如果主键不是 `id`，还需要配置：

```php
protected $primaryKey = 'task_id';
```

- 如果主键是字符串，例如 UUID，需要：

```php
public $incrementing = false;
protected $keyType = 'string';
```

TaskHub 当前主键名仍是 `id`，所以不需要配置 `$primaryKey`。

### 8.4 配置 `$fillable`：控制哪些字段允许批量写入

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

作用：

- 限制哪些字段可以通过批量赋值写入。
- 防止外部请求把不应该写的字段一起塞进 Model。

什么是批量赋值：

```php
Task::create($request->all());
```

或者：

```php
$task->fill($data);
```

如果没有保护，用户提交的数据里可能包含：

```php
[
    'status' => 'COMPLETED',
    'version' => 999,
]
```

这会造成严重问题。所以 Laravel 提供 `$fillable` 和 `$guarded`。

两种写法：

```php
// 白名单：只允许这些字段批量写入。
protected $fillable = ['title', 'description'];
```

```php
// 黑名单：不允许这些字段批量写入。
protected $guarded = ['id'];
```

TaskHub 当前选择 `$fillable`，原因：

- 字段清晰可见。
- 对新开发者更直观。
- MVP 阶段宁可写得明确，不追求省几行代码。

注意：

- `$fillable` 不是业务校验。
- 它不能替代 Request Validation。
- 它只是 Eloquent 的批量赋值保护。

后续真正开发发布任务时，仍然需要单独写请求校验。

### 8.5 配置 `casts()`：让数据库字段变成合适的 PHP 类型

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

作用：

- 告诉 Laravel 从数据库取出字段后，应该转成什么 PHP 类型。
- 保存时也会按指定类型进行转换。

TaskHub 中几个重要类型：

| 数据库字段 | 数据库类型 | casts 配置 | PHP 中的表现 |
|---|---|---|---|
| `payment_account_snapshot` | `JSON` | `array` | PHP 数组 |
| `created_by_snapshot` | `JSON` | `array` | PHP 数组 |
| `budget` | `DECIMAL(18,2)` | `decimal:2` | 保留 2 位小数的字符串 |
| `expected_delivery` | `DATE` | `date:Y-m-d` | 日期对象，展示为年月日 |
| `bidding_deadline` | `TIMESTAMP` | `datetime` | 日期时间对象 |

为什么 JSON 要 cast 成 `array`：

数据库中保存的是：

```json
{
  "displayName": "张三",
  "departmentName": "开发一部"
}
```

配置后可以这样读取：

```php
$task->created_by_snapshot['displayName'] ?? $task->created_by;
```

如果不配置，取出来可能是 JSON 字符串，每次都要手动 `json_decode()`。

为什么金额用 `decimal:2`：

- 数据库金额是 `DECIMAL(18,2)`。
- 不使用 `float` 或 `double`，避免精度误差。
- Laravel 的 `decimal:2` 会尽量保留十进制展示精度。

注意：在 Laravel 中，`decimal` cast 通常返回字符串形式的小数，而不是浮点数。这是好事，因为金额不应该随便用浮点数计算。

为什么日期要区分 `date` 和 `datetime`：

- `expected_delivery`、`final_delivery` 只关心自然日，适合 `DATE` / `date:Y-m-d`。
- `bidding_deadline`、`completed_at` 是具体发生时间，适合 `TIMESTAMP` / `datetime`。

Spring Boot 对照：

| Laravel casts | Java 常见类型 |
|---|---|
| `array` | `Map<String, Object>` / JSON Object |
| `decimal:2` | `BigDecimal` |
| `date:Y-m-d` | `LocalDate` |
| `datetime` | `LocalDateTime` / `Instant` |

### 8.6 配置关系：让表之间的连接可读

```php
public function bids(): HasMany
{
    return $this->hasMany(Bid::class, 'task_id');
}
```

作用：

- 描述 `task` 表和其他表之间的关联。
- 让代码可以用对象方式访问关联数据。

例如，一个任务有多个投标：

```php
$task = Task::query()->first();
$bids = $task->bids;
```

这背后对应 SQL 逻辑：

```sql
select * from bid where task_id = ?;
```

常见关系类型：

| Eloquent 关系 | 含义 | TaskHub 示例 |
|---|---|---|
| `hasMany` | 当前表一条记录，对方表多条记录 | 一个 Task 有多个 Bid |
| `belongsTo` | 当前表保存外键，属于对方一条记录 | Task 属于一个中标 Bid |
| `morphMany` | 多态一对多 | Task 可以有关联附件 |

TaskHub 中 `Task` 的关系示例：

```php
public function assignedBid(): BelongsTo
{
    return $this->belongsTo(Bid::class, 'assigned_bid_id');
}

public function bids(): HasMany
{
    return $this->hasMany(Bid::class, 'task_id');
}

public function deliveries(): HasMany
{
    return $this->hasMany(TaskDelivery::class, 'task_id');
}
```

为什么关系方法要写返回类型：

```php
public function bids(): HasMany
```

好处：

- IDE 能提示方法和返回值。
- 读代码时知道这是一对多。
- 静态分析更容易发现错误。

重要提醒：

- 关系只表达结构，不写业务流程。
- “选标时复制 BidMember 到 TaskAssignee”这种业务流程不应该写进 Model 关系方法。
- Phase 1 中，Model 保持干净，只做表映射、类型转换和关系表达。

### 8.7 完整 Task Model 应该长什么样

当前仓库的 `Task.php` 已经包含完整字段、casts 和关系。学习时你不需要一次背完，但要能看懂它分成四块：

```php
class Task extends Model
{
    // 1. 表和主键配置。
    protected $table = 'task';
    public $incrementing = false;
    protected $keyType = 'int';

    // 2. 允许批量写入的字段。
    protected $fillable = [
        'id',
        'title',
        'description',
        // ...
    ];

    // 3. 数据库字段到 PHP 类型的转换。
    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'created_by_snapshot' => 'array',
            'bidding_deadline' => 'datetime',
        ];
    }

    // 4. 和其他表的关系。
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'task_id');
    }
}
```

看 Model 时按这个顺序读，会比从上到下一行行硬读更容易理解。

### 8.8 这一节你需要真正记住什么

先记住 5 件事：

1. `$table`：表名不符合 Laravel 默认复数规则时必须写。
2. `$incrementing` / `$keyType`：主键生成方式和类型。
3. `$fillable`：批量写入白名单，不是业务校验。
4. `casts()`：把 JSON、金额、日期变成更适合 PHP 使用的类型。
5. 关系方法：表达表之间的结构，不承载业务流程。

等你后面写 Controller 查询数据时，会反复用到这些配置。

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
| `TaskhubUserRole` | `taskhub_user_role` | TaskHub 用户角色 |

## 16. 下一章入口

完成本章后，项目中应该已经有 `app/Models/Task.php` 等 Model。

下一章进入 [08-最小业务闭环](./08-最小业务闭环.md)，通过一个只读任务列表学习：

```text
Route
↓
Controller
↓
Task Model
↓
Inertia Props
↓
React Page
```

如果你从空目录跟着做，进入第 08 章前至少确认：

```bash
test -f database/schema.sql
test -f app/Models/Task.php
```
