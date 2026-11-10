-- 为任务时间线补充操作人姓名快照。
-- 现有 task_event.operator_id 继续用于人员标识和审计，姓名只负责历史展示。
ALTER TABLE `task_event`
  ADD COLUMN `operator_name` VARCHAR(100) NULL COMMENT '操作人姓名快照；系统事件可为空'
  AFTER `operator_id`;
