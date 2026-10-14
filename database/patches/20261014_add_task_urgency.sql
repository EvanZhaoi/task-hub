-- ============================================================
-- TaskHub 已有数据库升级脚本：增加任务紧急度
-- 适用范围：已经执行过旧版 database/schema.sql 的 MySQL 8 数据库。
-- 注意：该脚本只需要执行一次；全新数据库直接执行最新 schema.sql 即可。
-- ============================================================

ALTER TABLE `task`
  ADD COLUMN `urgency` VARCHAR(20) NOT NULL DEFAULT 'MEDIUM'
    COMMENT '紧急度：LOW/MEDIUM/HIGH' AFTER `complexity`,
  ADD CONSTRAINT `CHK_task_urgency`
    CHECK (`urgency` IN ('LOW', 'MEDIUM', 'HIGH'));
