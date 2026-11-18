-- ============================================================
-- TaskHub 已有数据库升级脚本：增加任务影响范围
-- 适用范围：已经存在 task 表的 MySQL 8 数据库。
-- 默认值 DEPARTMENT 用于兼容升级前已经发布的任务。
-- 注意：该脚本只执行一次；全新数据库直接执行最新 schema.sql。
-- ============================================================

ALTER TABLE `task`
  ADD COLUMN `impact_scope` VARCHAR(20) NOT NULL DEFAULT 'DEPARTMENT'
    COMMENT '影响范围：DEPARTMENT 本部门 / DIVISION 本 Division / COMPANY 全公司' AFTER `is_urgent`,
  ADD CONSTRAINT `CHK_task_impact_scope`
    CHECK (`impact_scope` IN ('DEPARTMENT', 'DIVISION', 'COMPANY'));
