# 17-发布任务Service重构

## 本章目标

本章把“发布任务”的核心业务流程从 `TaskController` 移动到 `CreateTaskService`。

这次不是新增功能，而是一次必要的结构整理。原因是发布任务已经不只是“接收一个表单然后写一张表”，它现在包含：

- 清洗 Tiptap 提交的富文本 HTML。
- 调用外部付款账号接口获取账号快照。
- 创建 `task` 主记录。
- 写入 `TASK_CREATED` 和 `TASK_PUBLISHED` 两条事件。
- 保存多个外部附件 ID 和名称到 `attachment_ref`。

> 最新说明：第 18 章已经把发布任务附件从 `attachmentIds` 字符串升级为 `attachments: [{ id, name }]` 结构，并在 `attachment_ref` 中保存 `attachment_name`。本章早期代码片段用于理解 Service 重构思路，附件字段以当前代码和第 18 章为准。
- 使用数据库事务保证这些写入要么全部成功，要么全部失败。

这些已经属于完整业务动作，不应该继续堆在 Controller 中。

## 学习目标

完成本章后，你应该理解：

- Laravel Controller 应该负责什么。
- 为什么涉及事务、多张表和外部接口时要抽 Service。
- Laravel 的 Service 不需要像 Spring Boot 那样手动加 `@Service`。
- Laravel 服务容器如何自动注入 `CreateTaskService`。
- 为什么不要为 Eloquent 再额外加 Repository。

## 最终效果

修改后调用流程变为：

```text
浏览器提交发布任务表单
↓
TaskController::store()
↓
StoreTaskRequest 校验
↓
CurrentUserService 获取当前登录人和 accessToken
↓
CreateTaskService::execute()
↓
PaymentAccountClient 查询付款账号
↓
RichTextSanitizer 清洗 description
↓
DB::transaction()
↓
创建 task / task_event / attachment_ref
↓
返回任务大厅
```

Controller 变薄，打开 `CreateTaskService` 就能完整看到“发布任务”这个业务动作的执行顺序。

## 涉及文件

| 文件 | 作用 |
|---|---|
| `app/Services/CreateTaskService.php` | 新增发布任务业务服务 |
| `app/Http/Controllers/TaskController.php` | `store()` 改为调用 Service |
| `docs/development/13-开发任务清单.md` | 标记 CreateTaskService 重构已完成 |
| `docs/development/README.md` | 增加本章入口 |

## 实际执行命令

命令执行目录：项目根目录。

```bash
# 使用 Laravel Artisan 生成普通 PHP 类。
# 这里不手写空文件，是为了保持 Laravel 项目的统一创建方式。
php artisan make:class Services/CreateTaskService
```

预期输出：

```text
INFO  Class [app/Services/CreateTaskService.php] created successfully.
```

生成后的默认文件很简单：

```php
<?php

namespace App\Services;

class CreateTaskService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
}
```

这个空类还没有业务意义，下一步要把发布任务流程移进去。

## 每一步操作

### 第 1 步：新增 CreateTaskService 依赖

编辑：

```text
app/Services/CreateTaskService.php
```

核心依赖如下：

```php
use App\Integrations\Payment\PaymentAccount;
use App\Integrations\Payment\PaymentAccountClient;
use App\Integrations\Payment\PaymentAccountException;
use App\Integrations\Sso\SsoUser;
use App\Models\AttachmentRef;
use App\Models\Task;
use App\Models\TaskEvent;
use Illuminate\Support\Facades\DB;
```

为什么这样做：

- `PaymentAccountClient` 负责对接外部付款账号列表。
- `RichTextSanitizer` 负责清洗富文本 HTML。
- `SnowflakeId` 负责生成业务主键。
- `Task`、`TaskEvent`、`AttachmentRef` 是本次发布任务要写入的三类业务数据。
- `DB::transaction()` 保证发布任务的多表写入一致。

### 第 2 步：通过构造函数注入公共能力

代码：

```php
public function __construct(
    private readonly PaymentAccountClient $paymentAccounts,
    private readonly RichTextSanitizer $richTextSanitizer,
    private readonly SnowflakeId $ids,
) {}
```

解释：

- Laravel 会通过服务容器自动创建这些对象。
- 这里不需要写 `new PaymentAccountClient()`。
- 也不需要像 Spring Boot 那样在类上写 `@Service`。
- `readonly` 表示这些依赖初始化后不再被替换，减少误修改。

Spring Boot 对照：

```java
@Service
public class CreateTaskService {
    private final PaymentAccountClient paymentAccounts;

    public CreateTaskService(PaymentAccountClient paymentAccounts) {
        this.paymentAccounts = paymentAccounts;
    }
}
```

Laravel 的差异：

- Laravel 不靠注解扫描这个类。
- 只要构造函数类型明确，Controller 或其它类需要它时，服务容器会自动解析。

### 第 3 步：编写 execute() 作为业务入口

`execute()` 是这个 Service 的公开入口：

```php
/**
 * 执行发布招标任务流程。
 *
 * @param  array<string, mixed>  $validated  StoreTaskRequest 已校验通过的表单数据。
 * @param  list<string>  $attachmentIds  已解析并去重后的外部附件 ID。
 * @throws PaymentAccountException 当付款账号接口不可用或账号不存在时抛出，由 Controller 转成表单错误。
 */
public function execute(array $validated, array $attachmentIds, SsoUser $user, string $accessToken): void
{
    // 富文本必须在入库前清洗；前端 Tiptap 只负责编辑体验，不是可信安全边界。
    $validated['description'] = $this->richTextSanitizer->clean($validated['description'] ?? null);

    // 付款账号是外部主数据，前端只提交 ID；名称和部门快照必须由后端从外部账号列表中匹配。
    // 外部查询放在事务外，避免数据库事务等待网络请求。
    $paymentAccount = $this->paymentAccounts->fetchById(
        (string) $validated['paymentAccountId'],
        $accessToken,
    );

    DB::transaction(function () use ($attachmentIds, $paymentAccount, $user, $validated): void {
        $task = $this->createTask($validated, $paymentAccount, $user);

        $this->createTaskCreatedEvent($task, $user);
        $this->createTaskPublishedEvent($task, $user, count($attachmentIds));
        $this->saveAttachments($task, $attachmentIds, $user);
    });
}
```

为什么外部接口放在事务外：

- 数据库事务应该尽量短。
- 外部接口可能慢、超时或失败。
- 如果先开启事务再等外部接口，会让数据库锁持有时间变长。

为什么清洗富文本放在 Service：

- 清洗是发布任务写入前的业务安全步骤。
- Controller 不应该理解 Tiptap 或 HTML 白名单细节。

### 第 4 步：把创建 Task 拆成 private 方法

代码：

```php
/**
 * 创建 task 主表记录。
 *
 * 当前发布入口只创建招标任务：
 * assignment_type 固定为 BIDDING，发布后直接进入 OPEN。
 *
 * @param  array<string, mixed>  $validated
 */
private function createTask(array $validated, PaymentAccount $paymentAccount, SsoUser $user): Task
{
    $employeeNo = $user->employeeNo();

    return Task::query()->create([
        'id' => $this->ids->next(),
        'title' => $validated['title'],
        'description' => $validated['description'] ?? null,
        'payment_account_id' => $paymentAccount->accountId(),
        'payment_account_snapshot' => $this->paymentAccountSnapshot($paymentAccount),
        'budget' => $validated['budget'],
        'expected_delivery' => $validated['expectedDelivery'],
        'bidding_deadline' => $validated['biddingDeadline'],
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => $validated['complexity'],
        'created_by' => $employeeNo,
        'created_by_snapshot' => $this->createdBySnapshot($user),
        'version' => 0,
        'updated_by' => $employeeNo,
    ]);
}
```

为什么这个方法是 `private`：

- 它只服务于发布任务流程。
- 其它业务模块不应该单独调用“只创建 task，不写事件”的半成品方法。
- 这符合当前规范：只在本业务流程内部复用的方法，优先放在当前 Service 里。

### 第 5 步：把事件写入拆成两个 private 方法

发布任务会写两条事件：

- `TASK_CREATED`：任务第一次进入系统。
- `TASK_PUBLISHED`：任务进入招标中。

这样做的原因：

- 当前 MVP 发布时没有草稿步骤，但数据库设计保留了草稿状态。
- 未来如果增加草稿，`TASK_PUBLISHED` 可以继续表达“从草稿发布到招标中”。
- TaskEvent 是业务时间线，不用于反推 Task 当前状态。

### 第 6 步：把附件引用写入拆成 private 方法

代码结构：

```php
private function saveAttachments(Task $task, array $attachmentIds, SsoUser $user): void
{
    if ($attachmentIds === []) {
        return;
    }

    $now = now();

    AttachmentRef::query()->insert(array_map(
        fn (string $attachmentId): array => [
            'id' => $this->ids->next(),
            'owner_type' => 'TASK',
            'owner_id' => $task->id,
            'attachment_id' => $attachmentId,
            'uploaded_by' => $user->employeeNo(),
            'created_at' => $now,
        ],
        $attachmentIds,
    ));
}
```

为什么用 `insert()`：

- 附件 ID 已经由 `StoreTaskRequest::attachmentIds()` 去重。
- 一次发布可能有多个附件。
- 批量插入比循环多次 `create()` 更直接。

为什么不抽成通用 `AttachmentService`：

- 当前只有发布任务用到了附件保存。
- 后续投标、交付也用到附件时，如果逻辑稳定重复，再抽公共能力。
- 这是“第三次再抽”的原则，避免过早出现杂物型工具类。

### 第 7 步：修改 TaskController::store()

修改前，Controller 里直接做了：

```text
校验结果
↓
清洗 description
↓
查询付款账号
↓
DB::transaction()
↓
创建 task
↓
写事件
↓
写附件
```

修改后：

```php
public function store(
    StoreTaskRequest $request,
    CurrentUserService $currentUser,
    CreateTaskService $createTask,
): RedirectResponse {
    try {
        // Controller 只组织 HTTP 层输入，把完整发布流程交给 CreateTaskService。
        $createTask->execute(
            validated: $request->validated(),
            attachmentIds: $request->attachmentIds(),
            user: $currentUser->user(),
            accessToken: $currentUser->accessToken(),
        );
    } catch (PaymentAccountException $exception) {
        throw ValidationException::withMessages([
            'paymentAccountId' => $exception->getMessage(),
        ]);
    }

    return redirect()
        ->route('tasks.index')
        ->with('success', '任务已发布。');
}
```

Controller 现在只保留：

- 使用 `StoreTaskRequest` 获取已校验数据。
- 使用 `CurrentUserService` 获取当前用户上下文。
- 调用 `CreateTaskService`。
- 把付款账号异常转换成表单字段错误。
- 返回跳转响应。

这正是 Controller 应该承担的 HTTP 层职责。

## 核心原理解释

### Controller 为什么要变薄

Controller 是 HTTP 入口。它应该回答：

- 这个请求由谁处理？
- 请求参数是否合法？
- 当前用户是谁？
- 调用哪个业务动作？
- 最后返回什么响应？

它不应该长期承担：

- 多表事务。
- 外部接口调用细节。
- 复杂状态流转。
- 事件记录。
- 快照组装。

如果这些都放在 Controller，后续投标、选标、交付继续加上来后，Controller 很快会变成一个难以维护的大文件。

### Service 不是 Repository

`CreateTaskService` 表示一个业务动作，不是数据访问层。

它可以直接使用 Eloquent：

```php
Task::query()->create([...]);
TaskEvent::query()->create([...]);
```

不需要再套一层：

```text
TaskRepository
TaskEventRepository
AttachmentRefRepository
```

原因：

- Eloquent 已经是 Laravel 官方 ORM。
- 当前 MVP 没有复杂的数据源切换需求。
- Repository 会增加文件数量和调用链，但暂时没有实际收益。

### Service 应该多大

本章的 Service 保持一个原则：

```text
一个重要业务动作 = 一个 Service
```

所以 `CreateTaskService` 可以包含几个 private 方法：

- `createTask()`
- `createTaskCreatedEvent()`
- `createTaskPublishedEvent()`
- `saveAttachments()`
- `createdBySnapshot()`
- `paymentAccountSnapshot()`

这些方法共同服务于“发布任务”这个动作。打开这个文件，应该能从上到下读懂发布任务完整流程。

## Laravel 与 Spring Boot 对照

| Laravel | Spring Boot | 说明 |
|---|---|---|
| Controller 方法注入 Service | Controller 构造器注入 Service | Laravel 可以在方法参数中直接注入服务 |
| FormRequest | `@Valid` DTO / RequestBody | Laravel 把校验规则放在 Request 类中 |
| Service 普通 PHP 类 | `@Service` Bean | Laravel 不需要注解，服务容器按类型解析 |
| Eloquent Model | JPA Entity + Repository 的部分能力 | Eloquent 同时承担模型映射和查询入口 |
| `DB::transaction()` | `@Transactional` | Laravel 显式包裹事务闭包 |
| `ValidationException::withMessages()` | BindingResult / FieldError | 用字段错误返回给表单 |

注意：Laravel 的 Eloquent 和 JPA 不完全相同。Eloquent 更偏 Active Record，允许通过 Model 直接查询和写入；JPA 更常见的是 Entity + Repository。

## 如何验证

命令执行目录：项目根目录。

```bash
# 检查 PHP 语法。
php -l app/Services/CreateTaskService.php
php -l app/Http/Controllers/TaskController.php

# 检查 TypeScript 类型。
npm run typecheck

# 检查前端生产构建。
npm run build

# 执行后端 Feature Test。
php artisan test
```

浏览器验证：

1. 启动项目。
2. 访问 `http://127.0.0.1:8000/tasks`。
3. 点击“发布任务”。
4. 填写标题、富文本描述、预算、交期、截止时间、复杂度、付款账号和附件 ID。
5. 提交后应返回任务大厅，并看到成功提示。

数据库验证：

```sql
-- 查看最新任务。
select id, title, status, assignment_type, created_by
from task
order by created_at desc
limit 1;

-- 查看该任务事件。
select task_id, event_type, operator_id, created_at
from task_event
order by created_at desc
limit 5;

-- 查看任务附件引用。
select owner_type, owner_id, attachment_id
from attachment_ref
where owner_type = 'TASK'
order by created_at desc
limit 5;
```

## 常见错误

### 1. Target class does not exist

现象：

```text
Target class [App\Services\CreateTaskService] does not exist.
```

排查：

- 确认文件路径是 `app/Services/CreateTaskService.php`。
- 确认 namespace 是 `App\Services`。
- 执行：

```bash
composer dump-autoload
```

### 2. PaymentAccountException 没有被转换成字段错误

现象：

付款账号接口失败时页面直接报 500。

排查：

- Controller 是否 `use App\Integrations\Payment\PaymentAccountException;`
- `store()` 是否用 `try/catch` 包住 `$createTask->execute(...)`。
- catch 中是否抛出 `ValidationException::withMessages(['paymentAccountId' => ...])`。

### 3. 任务创建了但事件或附件没写入

排查：

- 确认 `CreateTaskService::execute()` 中 `createTask()`、`createTaskCreatedEvent()`、`createTaskPublishedEvent()`、`saveAttachments()` 都在同一个 `DB::transaction()` 闭包内。
- 如果事务内任何一步抛异常，前面的写入也会回滚。

### 4. Controller 又开始变厚

后续新增业务时，如果 Controller 方法出现这些迹象，就应该抽 Service：

- 有数据库事务。
- 同时操作两张以上业务表。
- 调用外部接口后再写库。
- 大量状态判断。
- 方法超过约 30 到 50 行。

## 操作检查清单

- [ ] 已用 `php artisan make:class Services/CreateTaskService` 生成 Service。
- [ ] `CreateTaskService::execute()` 包含发布任务完整流程。
- [ ] 外部付款账号查询放在数据库事务外。
- [ ] `task`、`task_event`、`attachment_ref` 写入放在同一个事务内。
- [ ] `TaskController::store()` 只负责组织请求、调用 Service 和返回 Redirect。
- [ ] 没有新增 Repository、DAO、DDD 分层。
- [ ] `php artisan test` 通过。
- [ ] `npm run typecheck` 和 `npm run build` 通过。

## 本章总结

本章没有改变用户看到的功能，但改变了后端业务代码的组织方式。

发布任务现在符合 TaskHub 后续开发规范：

- Controller 处理 HTTP。
- FormRequest 处理校验。
- CurrentUserService 处理当前登录人。
- CreateTaskService 处理完整业务动作。
- Eloquent Model 负责表映射和关系。
- 事务由业务 Service 控制。

这会让后续投标、撤回投标、选标、交付、验收等功能更容易按同样模式推进。

## 下一章预告

下一步建议开始开发任务详情页。

原因：

- 投标、选标、交付、变更都需要依附于某个任务详情。
- 详情页可以先做只读展示：任务基本信息、附件、事件时间线、投标区域占位。
- 有了详情页后，再逐步加入投标模态框和投标事务。
