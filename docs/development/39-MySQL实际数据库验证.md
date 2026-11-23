# 39 - MySQL 实际数据库验证

## 1. 本章目标

前面的 Pest Feature Test 默认使用 SQLite 内存数据库。SQLite 适合快速验证 Controller、Service 和页面数据，但不能完全证明 MySQL 的 `CHECK`、外键、唯一索引、`DECIMAL`、`TIMESTAMP` 和字符集配置正确。

本章使用独立数据库 `taskhub_verify` 导入正式 `database/schema.sql`，在真实 MySQL 协议连接上验证数据库结构、Eloquent Model、核心业务 Service 和招标截止处理。

## 2. 验证边界

本次本机数据库服务器返回版本：

```text
9.6.0
```

因此本章已经完成 MySQL 向前兼容验证，但不能替代 MySQL 8 实机终验。正式测试环境仍需在 MySQL 8 上重复“建库导入”和“核心链路”两部分。

本次没有：

- 修改现有 `.env`。
- 修改或清空现有 `taskhub` 数据库。
- 把验证数据提交到数据库。
- 调用真实 SSO、人员、付款账号、附件或 SMTP 服务。

所有写入验证均放在最外层事务中，结束后统一回滚。

## 3. 最终效果

| 验证项 | 本机结果 |
| --- | --- |
| `schema.sql` 全量导入 | 通过 |
| Laravel MySQL 连接 | 通过 |
| 9 张正式业务表 | 通过 |
| InnoDB / utf8mb4_unicode_ci | 通过 |
| 22 个 CHECK 约束 | 已识别并验证生效 |
| 7 个外键 | 已识别并验证生效 |
| 7 个唯一约束 | 已识别并验证关键约束生效 |
| 全部 Eloquent Model 写入和 casts | 通过 |
| 招标发布 -> 联合投标 -> 选标 -> 完成 | 通过 |
| 直接指定成员发布 | 通过 |
| 无投标自动流标 | 通过 |
| 有投标截止后等待选标及幂等 | 通过 |
| 已选标任务截止扫描跳过 | 通过 |
| MySQL 8 精确版本终验 | 待测试环境执行 |

## 4. 涉及文件

| 文件 | 作用 |
| --- | --- |
| `database/schema.sql` | 全新数据库结构唯一标准 |
| `database/patches/*.sql` | 已有数据库升级时使用；不能用 schema 覆盖已有数据 |
| `config/database.php` | MySQL 连接、严格模式和 `+08:00` 时区配置 |
| `.env.example` | 标准数据库环境变量示例 |
| `app/Models/*.php` | Eloquent 表名、时间戳、casts 和关联映射 |
| `app/Services/CreateTaskService.php` | 招标发布事务 |
| `app/Services/PlaceBidService.php` | 投标事务 |
| `app/Services/SelectBidService.php` | 选标事务 |
| `app/Services/CreateDirectTaskService.php` | 直接指派事务 |
| `app/Services/CompleteTaskService.php` | 第一期手动完成事务 |
| `app/Services/ExpireOpenTasksService.php` | 招标截止单任务处理事务 |

## 5. 创建独立验证库

命令执行目录：TaskHub 项目根目录。

正式 `schema.sql` 会删除并重建 `taskhub` 中的业务表，不能直接对已有开发库或生产库执行。验证时只替换 SQL 中的数据库名称，并把临时文件放在 `/tmp`：

```bash
# 只替换 CREATE DATABASE 和 USE，不修改仓库中的正式 schema.sql。
sed \
  -e 's/CREATE DATABASE IF NOT EXISTS `taskhub`/CREATE DATABASE IF NOT EXISTS `taskhub_verify`/' \
  -e 's/USE `taskhub`;/USE `taskhub_verify`;/' \
  database/schema.sql > /tmp/taskhub_verify.sql
```

随后使用 Laravel 当前数据库账号执行临时 SQL。本次实际执行成功，未发现建表顺序、外键创建顺序或 SQL 语法错误。

## 6. Laravel 连接验证

不需要改写 `.env`，可以只对当前命令覆盖数据库名：

```bash
# 显示 Laravel 实际识别的数据库连接。
DB_DATABASE=taskhub_verify php artisan db:show

# 使用正式 Task Model 查询 MySQL 表。
DB_DATABASE=taskhub_verify php artisan tinker
```

Tinker 中执行：

```php
// 返回 0 表示连接和 Task 表映射均正常，只是验证库还没有业务数据。
App\Models\Task::query()->count();
```

## 7. 数据库结构结果

### 7.1 正式表清单

验证库包含 9 张表：

```text
attachment_ref
bid
bid_member
task
task_assignee
task_change_request
task_delivery
task_event
taskhub_user_role
```

全部表满足：

```text
ENGINE = InnoDB
COLLATION = utf8mb4_unicode_ci
```

### 7.2 约束统计

从 `information_schema.TABLE_CONSTRAINTS` 实际读取：

| 类型 | 数量 |
| --- | ---: |
| PRIMARY KEY | 9 |
| UNIQUE | 7 |
| FOREIGN KEY | 7 |
| CHECK | 22 |

关键索引均存在：

- `bid(active_key)` 唯一。
- `bid_member(bid_id, user_id)` 唯一。
- `task_assignee(task_id, user_id)` 唯一。
- `attachment_ref(owner_type, owner_id, attachment_id)` 唯一。
- `task_delivery(task_id, delivery_no)` 唯一。
- Task 状态、发布人、负责人、付款账号、截止时间和复杂度查询索引存在。
- TaskEvent 的任务时间线和操作人查询索引存在。

### 7.3 字段规范

实际读取 `information_schema.COLUMNS` 后确认：

- `task.budget`、`task.final_amount`、`bid.amount` 均为 `DECIMAL(18,2)`。
- 12 个人员引用字段均为 `VARCHAR(32)`。
- `expected_delivery`、`final_delivery`、`delivery_date` 使用 `DATE`。
- `created_at`、`updated_at`、截止、完成、撤回、审批等具体时间均使用 `TIMESTAMP`。
- 所有表字段均存在中文注释，没有空字段注释。
- MySQL Session 时区为 `+08:00`。
- `STRICT_TRANS_TABLES`、`NO_ZERO_DATE` 等严格模式已开启。

## 8. 约束冒烟测试

验证数据先写入，再故意提交非法数据。测试确认：

1. `task.status = INVALID` 被 `CHECK` 拒绝。
2. 两条 Bid 使用相同 `active_key` 被唯一索引拒绝。
3. 同一 Bid 重复写入同一 `user_id` 被成员唯一索引拒绝。
4. Bid 引用不存在的 Task 被外键拒绝。
5. 最外层事务回滚后验证数据数量恢复为零。

验证时不仅检查异常，还检查回滚后的数据库状态，避免出现部分数据残留。

## 9. Eloquent Model 验证

本次使用正式 Model 在 MySQL Schema 上写入并读取：

- `Task`
- `Bid`
- `BidMember`
- `TaskAssignee`
- `TaskDelivery`
- `AttachmentRef`
- `TaskChangeRequest`
- `TaskEvent`
- `TaskhubUserRole`

检查结果：

- 没有 Model 误写不存在的 `updated_at`。
- Task/Bid/成员/附件/事件关联可以读取。
- JSON 快照和变更值可以转换为 PHP 数组。
- `DECIMAL` 保持两位精度。
- 日期和时间转换为预期的 Carbon 类型。
- `enabled` 和 `is_urgent` 正确转换为布尔值。

## 10. 核心业务 Service 验证

### 10.1 招标路径

实际调用顺序：

```text
CreateTaskService
-> PlaceBidService（OWNER + COLLABORATOR）
-> SelectBidService
-> CompleteTaskService
```

确认结果：

- 发布后 Task 为 `OPEN`。
- 同时写入 `TASK_CREATED`、`TASK_PUBLISHED` 和任务附件。
- 富文本中的 `<script>` 被后端清除。
- 联合投标写入一个 OWNER、一个 COLLABORATOR 和投标附件。
- 选标后 Bid 为 `ACCEPTED`，Task 为 `ASSIGNED`。
- BidMember 完整复制到 TaskAssignee。
- `primary_assignee_id` 与中标 OWNER 一致。
- 手动完成后 Task 为 `COMPLETED`，写入完成时间和 `TASK_COMPLETED`。

### 10.2 直接指派路径

实际调用 `CreateDirectTaskService`，确认：

- Task 创建后直接为 `ASSIGNED`。
- `assignment_type = DIRECT`。
- `assigned_bid_id` 为空。
- OWNER 和 COLLABORATOR 正确写入 TaskAssignee。
- 写入 `TASK_ASSIGNED` 事件。

外部付款账号和人员接口使用内存对象替代。本章验证的是 MySQL 数据结构与业务事务，不验证公司网络和外部系统可用性。

## 11. 招标截止规则验证

`ExpireOpenTasksService` 在正式 MySQL 表上确认：

| 场景 | 结果 |
| --- | --- |
| OPEN、已截止、没有 ACTIVE Bid | Task 变为 FAILED，version + 1 |
| OPEN、已截止、存在 ACTIVE Bid | Task 保持 OPEN，写入待选标事件 |
| 同一截止时间重复处理 | 不重复写事件或发送提醒 |
| ASSIGNED 且截止时间已过 | 直接跳过，不改状态、不写事件 |

全部数据在测试结束后回滚。

## 12. 与 Spring Boot 的概念对照

| Laravel / MySQL 验证 | Spring Boot 常见方式 |
| --- | --- |
| `DB_DATABASE=... php artisan ...` | 使用独立 profile / datasource URL |
| Eloquent Model 冒烟写入 | JPA Entity 集成测试 |
| `DB::transaction()` 外层回滚 | 测试方法上的 `@Transactional` |
| `information_schema` 查询 | JDBC 查询数据库元数据 |
| Service 真实调用、外部 Client 替身 | Spring Service 集成测试 + Mock Bean |
| Pest SQLite Feature Test | H2 快速测试 |
| MySQL 独立库验证 | Testcontainers 或测试环境 MySQL |

当前开发机不能下载额外依赖，因此没有引入 Testcontainers 或 Docker。使用独立数据库和事务回滚更符合当前环境。

## 13. 常见错误

### 13.1 对现有数据库执行 schema.sql

`schema.sql` 包含 `DROP TABLE`，只适用于全新初始化。已有数据库必须备份后按顺序执行 `database/patches`。

### 13.2 把 SQLite 测试通过当成 MySQL 通过

SQLite 对 CHECK、外键、类型和时间行为与 MySQL 不完全相同。两层测试都需要保留。

### 13.3 用 MySQL 9.x 结果代替 MySQL 8 终验

本机结果只能证明当前 SQL 在 9.6 上可执行。测试发布前必须在目标 MySQL 8 环境重复导入和核心链路。

### 13.4 验证脚本留下脏数据

写入检查必须使用独立数据库，并通过最外层事务回滚。不要在开发业务库中插入固定验证 ID。

## 14. 操作检查清单

- [x] 使用独立 `taskhub_verify` 数据库。
- [x] 正式 schema 全量导入成功。
- [x] 表、字符集、引擎、约束和索引检查通过。
- [x] 金额、人员、日期、时间和注释规范检查通过。
- [x] 全部 Eloquent Model 映射检查通过。
- [x] 招标和直接指派核心 Service 链路通过。
- [x] 截止处理与幂等规则通过。
- [x] 验证数据全部回滚。
- [ ] 在目标 MySQL 8 服务器重复执行。
- [ ] 使用真实 SSO、人员、账号、附件和 SMTP 完成人工验收。
- [ ] 在现有数据库副本演练 patches 升级。

## 15. 本章总结

当前 Schema、Model 和核心事务在本机真实 MySQL 连接上没有发现结构冲突，关键数据库约束也确实生效。唯一不能在本机关闭的检查项是“目标版本必须为 MySQL 8”，因为当前服务器实际版本为 9.6.0。

因此任务状态应记录为：**本机 MySQL 兼容验证完成，MySQL 8 测试环境终验待执行**。

## 16. 下一章预告

下一步进行常用桌面分辨率和关键页面人工检查，然后准备测试环境配置、部署步骤、日志检查和回滚说明。获得 MySQL 8 测试环境后，优先重复本章第 5、7、8、10、11 节。
