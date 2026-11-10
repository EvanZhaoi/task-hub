-- TaskHub 已有数据库一次性升级脚本：增加招标截止事件并优化定时扫描索引。
-- 适用数据库：MySQL 8。
-- 执行前请确认当前 task_event 约束名和 task 索引名仍与 database/schema.sql 旧版本一致。

-- 定时任务固定使用 status = OPEN，再按 bidding_deadline 做范围查询。
-- 将等值条件放在复合索引第一列，避免每分钟扫描全部历史任务。
ALTER TABLE `task`
  DROP INDEX `IDX_task_bidding_deadline_status`,
  ADD INDEX `IDX_task_status_bidding_deadline` (`status`, `bidding_deadline`);

-- MySQL 8 会执行 CHECK 约束；必须先扩展事件枚举，业务代码才能写入截止待选标事件。
ALTER TABLE `task_event`
  DROP CHECK `CHK_task_event_type`,
  ADD CONSTRAINT `CHK_task_event_type` CHECK (`event_type` IN (
    'TASK_CREATED', 'TASK_PUBLISHED', 'BID_SUBMITTED', 'BID_WITHDRAWN',
    'BID_ACCEPTED', 'TASK_ASSIGNED', 'CHANGE_REQUESTED', 'CHANGE_APPROVED',
    'CHANGE_REJECTED', 'DELIVERY_SUBMITTED', 'DELIVERY_ACCEPTED',
    'REVISION_REQUIRED', 'TASK_COMPLETED', 'TASK_CANCELLED', 'TASK_FAILED',
    'BIDDING_DEADLINE_EXTENDED', 'BIDDING_DEADLINE_REACHED'
  ));
