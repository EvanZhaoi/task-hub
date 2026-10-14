-- ============================================================
-- TaskHub 已有数据库升级脚本：增加“是否紧急”字段
-- 适用范围：尚未执行过三级 urgency 字段脚本的 MySQL 8 数据库。
-- 注意：该脚本只执行一次；全新数据库直接执行最新 schema.sql。
-- ============================================================

ALTER TABLE `task`
  ADD COLUMN `is_urgent` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '是否紧急：0 否 / 1 是' AFTER `complexity`,
  ADD CONSTRAINT `CHK_task_is_urgent`
    CHECK (`is_urgent` IN (0, 1));
