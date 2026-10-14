-- ============================================================
-- TaskHub 三级紧急度转换脚本
-- 适用范围：已经执行过旧版 urgency LOW/MEDIUM/HIGH 脚本的数据库。
-- 转换规则：HIGH -> 1，LOW/MEDIUM -> 0。
-- 注意：不要和 add_task_is_urgent.sql 重复执行。
-- ============================================================

ALTER TABLE `task`
  ADD COLUMN `is_urgent` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '是否紧急：0 否 / 1 是' AFTER `urgency`;

UPDATE `task`
SET `is_urgent` = CASE WHEN `urgency` = 'HIGH' THEN 1 ELSE 0 END;

ALTER TABLE `task`
  DROP CHECK `CHK_task_urgency`,
  DROP COLUMN `urgency`,
  ADD CONSTRAINT `CHK_task_is_urgent`
    CHECK (`is_urgent` IN (0, 1));
