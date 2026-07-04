-- ============================================================
-- TaskHub 数据库 Schema (MySQL 8.4)
-- 文档：docs/模块与数据库设计.md v0.7
-- 核心表：task / task_assignee / bid / bid_collaborator / attachment_ref / task_change_request / task_change_log
-- ============================================================

CREATE DATABASE IF NOT EXISTS `taskhub`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `taskhub`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `task_change_log`;
DROP TABLE IF EXISTS `task_change_request`;
DROP TABLE IF EXISTS `attachment_ref`;
DROP TABLE IF EXISTS `bid_collaborator`;
DROP TABLE IF EXISTS `bid`;
DROP TABLE IF EXISTS `task_assignee`;
DROP TABLE IF EXISTS `task`;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. task（任务主表）
-- ============================================================
CREATE TABLE `task` (
  `id`                 BIGINT UNSIGNED  NOT NULL             COMMENT '雪花 ID',
  `title`              VARCHAR(200)     NOT NULL             COMMENT '任务标题',
  `description`        LONGTEXT                              COMMENT '富文本描述（HTML）',
  `payment_account_id` VARCHAR(64)      NOT NULL             COMMENT '付款账号 ID（外部引用）',
  `budget`             DECIMAL(12, 2)   NOT NULL             COMMENT '预算金额',
  `final_amount`       DECIMAL(12, 2)                        COMMENT '最终成交金额（中标后填）',
  `expected_delivery`  TIMESTAMP        NOT NULL             COMMENT '期望交期',
  `final_delivery`     TIMESTAMP        NULL                 COMMENT '最终交期',
  `bidding_deadline`   TIMESTAMP        NULL                 COMMENT '招标截止时间（流标判定依据）',
  `status`             TINYINT          NOT NULL DEFAULT 0   COMMENT '0=DRAFT / 1=OPEN / 2=ASSIGNED / 3=COMPLETED / 4=FAILED / 5=CANCELLED',
  `is_direct`          TINYINT(1)       NOT NULL DEFAULT 0   COMMENT '是否直接指名 0=否 1=是',
  `complexity`         TINYINT          NOT NULL DEFAULT 1   COMMENT '0=LOW 简单 / 1=MEDIUM 中等复杂 / 2=HIGH 高度复杂',
  `created_by`         BIGINT UNSIGNED  NOT NULL             COMMENT '发布者 user_id',
  `assigned_bid_id`    BIGINT UNSIGNED                       COMMENT '中标投标 ID（招标模式冗余）',
  `assigned_user_id`   BIGINT UNSIGNED                       COMMENT '当前主负责人 user_id（招标中标者或直接指名人）',
  `completed_at`       TIMESTAMP        NULL                 COMMENT '完成时间',
  `created_at`         TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`         BIGINT UNSIGNED                       COMMENT '最后修改人',
  `deleted_at`         TIMESTAMP        NULL                 COMMENT '软删时间',
  PRIMARY KEY (`id`),
  KEY `IDX_status_created`       (`status`, `created_at` DESC),
  KEY `IDX_created_by_status`    (`created_by`, `status`),
  KEY `IDX_assigned_user_status` (`assigned_user_id`, `status`),
  KEY `IDX_payment_account`      (`payment_account_id`, `status`),
  KEY `IDX_complexity_status`    (`complexity`, `status`),
  KEY `IDX_title`                (`title`),
  KEY `IDX_deleted`              (`deleted_at`),
  CONSTRAINT `CHK_task_budget_non_negative` CHECK (`budget` >= 0),
  CONSTRAINT `CHK_task_final_amount_non_negative` CHECK (`final_amount` IS NULL OR `final_amount` >= 0),
  CONSTRAINT `CHK_task_status` CHECK (`status` BETWEEN 0 AND 5),
  CONSTRAINT `CHK_task_complexity` CHECK (`complexity` BETWEEN 0 AND 2),
  CONSTRAINT `CHK_task_is_direct` CHECK (`is_direct` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务主表';


-- ============================================================
-- 2. task_assignee（任务最终参与人）
--    用于记录直接指名或中标后实际参与交付的人。
-- ============================================================
CREATE TABLE `task_assignee` (
  `id`          BIGINT UNSIGNED NOT NULL             COMMENT '雪花 ID',
  `task_id`     BIGINT UNSIGNED NOT NULL             COMMENT '任务 ID',
  `user_id`     BIGINT UNSIGNED NOT NULL             COMMENT '参与人 user_id',
  `role`        TINYINT         NOT NULL DEFAULT 1   COMMENT '0=OWNER 主负责人 / 1=COLLABORATOR 协作人',
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`  TIMESTAMP       NULL                 COMMENT '软删时间',
  `active_user_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `deleted_at` IS NULL THEN `user_id` ELSE NULL END
  ) STORED COMMENT '仅未删除时用于唯一约束',
  `active_owner_flag` TINYINT GENERATED ALWAYS AS (
    CASE WHEN `role` = 0 AND `deleted_at` IS NULL THEN 1 ELSE NULL END
  ) STORED COMMENT '仅主负责人且未删除时用于唯一约束',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_task_active_user` (`task_id`, `active_user_id`),
  UNIQUE KEY `UNIQ_task_active_owner` (`task_id`, `active_owner_flag`),
  KEY `IDX_task_id` (`task_id`),
  KEY `IDX_user_role` (`user_id`, `role`),
  KEY `IDX_deleted` (`deleted_at`),
  CONSTRAINT `CHK_task_assignee_role` CHECK (`role` BETWEEN 0 AND 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务最终参与人';


-- ============================================================
-- 3. bid（投标表）
-- ============================================================
CREATE TABLE `bid` (
  `id`             BIGINT UNSIGNED NOT NULL             COMMENT '雪花 ID',
  `task_id`        BIGINT UNSIGNED NOT NULL             COMMENT '任务 ID',
  `bidder_id`      BIGINT UNSIGNED NOT NULL             COMMENT '投标主对接人 user_id',
  `amount`         DECIMAL(12, 2)  NOT NULL             COMMENT '投标金额',
  `delivery_date`  TIMESTAMP       NOT NULL             COMMENT '承诺交期',
  `proposal`       TEXT                                 COMMENT '方案描述',
  `status`         TINYINT        NOT NULL DEFAULT 0    COMMENT '0=ACTIVE / 1=WITHDRAWN / 2=ACCEPTED / 3=LOST',
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     TIMESTAMP      NULL                  COMMENT '软删时间',
  `active_bidder_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` = 0 AND `deleted_at` IS NULL THEN `bidder_id` ELSE NULL END
  ) STORED COMMENT '仅 ACTIVE 且未删除时用于唯一约束',
  PRIMARY KEY (`id`),
  KEY `IDX_task_status` (`task_id`, `status`),
  KEY `IDX_bidder_status` (`bidder_id`, `status`),
  UNIQUE KEY `UNIQ_task_active_bidder` (`task_id`, `active_bidder_id`),
  KEY `IDX_deleted` (`deleted_at`),
  CONSTRAINT `CHK_bid_amount_non_negative` CHECK (`amount` >= 0),
  CONSTRAINT `CHK_bid_status` CHECK (`status` BETWEEN 0 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='投标表';


-- ============================================================
-- 4. bid_collaborator（投标协作人）
-- ============================================================
CREATE TABLE `bid_collaborator` (
  `id`          BIGINT UNSIGNED NOT NULL             COMMENT '雪花 ID',
  `bid_id`      BIGINT UNSIGNED NOT NULL             COMMENT '投标 ID',
  `user_id`     BIGINT UNSIGNED NOT NULL             COMMENT '协作成员 user_id',
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`  TIMESTAMP       NULL                 COMMENT '软删时间',
  `active_user_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `deleted_at` IS NULL THEN `user_id` ELSE NULL END
  ) STORED COMMENT '仅未删除时用于唯一约束',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_bid_active_user` (`bid_id`, `active_user_id`),
  KEY `IDX_bid_id` (`bid_id`),
  KEY `IDX_user_id` (`user_id`),
  KEY `IDX_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='投标协作人';


-- ============================================================
-- 5. attachment_ref（附件引用）
--    文件上传 / 下载由外部接口负责，本表只记录外部附件 ID。
--    支持任务和投标各自关联多个附件。
-- ============================================================
CREATE TABLE `attachment_ref` (
  `id`            BIGINT UNSIGNED NOT NULL             COMMENT '雪花 ID',
  `owner_type`    TINYINT         NOT NULL             COMMENT '0=TASK / 1=BID',
  `owner_id`      BIGINT UNSIGNED NOT NULL             COMMENT '所属业务 ID：task.id 或 bid.id',
  `attachment_id` VARCHAR(128)    NOT NULL             COMMENT '外部附件 ID',
  `uploaded_by`   BIGINT UNSIGNED NOT NULL             COMMENT '上传者 user_id',
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`    TIMESTAMP       NULL                 COMMENT '软删时间',
  `active_attachment_id` VARCHAR(128) GENERATED ALWAYS AS (
    CASE WHEN `deleted_at` IS NULL THEN `attachment_id` ELSE NULL END
  ) STORED COMMENT '仅未删除时用于唯一约束',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_owner_active_attachment` (`owner_type`, `owner_id`, `active_attachment_id`),
  KEY `IDX_owner` (`owner_type`, `owner_id`),
  KEY `IDX_attachment_id` (`attachment_id`),
  KEY `IDX_deleted` (`deleted_at`),
  CONSTRAINT `CHK_attachment_owner_type` CHECK (`owner_type` BETWEEN 0 AND 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='附件引用';


-- ============================================================
-- 6. task_change_request（任务变更请求 / 双方确认）
-- ============================================================
CREATE TABLE `task_change_request` (
  `id`           BIGINT UNSIGNED NOT NULL             COMMENT '雪花 ID',
  `task_id`      BIGINT UNSIGNED NOT NULL             COMMENT '任务 ID',
  `change_type`  TINYINT         NOT NULL             COMMENT '0=AMOUNT / 1=DELIVERY / 2=DESCRIPTION / 3=EXTENSION / 4=CANCEL',
  `old_value`    JSON                                 COMMENT '变更前值',
  `new_value`    JSON                                 COMMENT '变更后值',
  `reason`       VARCHAR(500)                         COMMENT '原因',
  `initiator_id` BIGINT UNSIGNED NOT NULL             COMMENT '发起人 user_id',
  `approver_id`  BIGINT UNSIGNED NOT NULL             COMMENT '待确认人 user_id',
  `status`       TINYINT         NOT NULL DEFAULT 0   COMMENT '0=PENDING / 1=APPROVED / 2=REJECTED / 3=CANCELLED',
  `responded_at` TIMESTAMP       NULL                 COMMENT '确认/拒绝时间',
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`   TIMESTAMP       NULL                 COMMENT '软删时间',
  `pending_change_type` TINYINT GENERATED ALWAYS AS (
    CASE WHEN `status` = 0 AND `deleted_at` IS NULL THEN `change_type` ELSE NULL END
  ) STORED COMMENT '仅 PENDING 且未删除时用于唯一约束',
  PRIMARY KEY (`id`),
  KEY `IDX_task_status` (`task_id`, `status`),
  KEY `IDX_approver_status` (`approver_id`, `status`),
  UNIQUE KEY `UNIQ_task_pending_change` (`task_id`, `pending_change_type`),
  KEY `IDX_deleted` (`deleted_at`),
  CONSTRAINT `CHK_change_request_type` CHECK (`change_type` BETWEEN 0 AND 4),
  CONSTRAINT `CHK_change_request_status` CHECK (`status` BETWEEN 0 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务变更请求';


-- ============================================================
-- 7. task_change_log（任务变更记录 / 审计日志，不软删）
-- ============================================================
CREATE TABLE `task_change_log` (
  `id`           BIGINT UNSIGNED NOT NULL             COMMENT '雪花 ID',
  `task_id`      BIGINT UNSIGNED NOT NULL             COMMENT '任务 ID',
  `change_type`  TINYINT         NOT NULL             COMMENT '0=AMOUNT / 1=DELIVERY / 2=DESCRIPTION / 3=EXTENSION / 4=CANCEL',
  `old_value`    JSON                                 COMMENT '变更前值',
  `new_value`    JSON                                 COMMENT '变更后值',
  `reason`       VARCHAR(500)                         COMMENT '原因',
  `agreed_by`    JSON                                 COMMENT '双方 user_id 列表 [u1, u2]',
  `request_id`   BIGINT UNSIGNED                      COMMENT '来源变更请求 ID（如有）',
  `created_by`   BIGINT UNSIGNED                      COMMENT '发起人',
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `IDX_task_created` (`task_id`, `created_at` DESC),
  KEY `IDX_request_id` (`request_id`),
  CONSTRAINT `CHK_change_log_type` CHECK (`change_type` BETWEEN 0 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务变更记录';


-- ============================================================
-- 跨表外键（处理 task <-> bid 的引用顺序）
-- ============================================================
ALTER TABLE `task`
  ADD CONSTRAINT `FK_task_assigned_bid` FOREIGN KEY (`assigned_bid_id`) REFERENCES `bid` (`id`) ON DELETE SET NULL;

ALTER TABLE `task_assignee`
  ADD CONSTRAINT `FK_task_assignee_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE CASCADE;

ALTER TABLE `bid`
  ADD CONSTRAINT `FK_bid_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE CASCADE;

ALTER TABLE `bid_collaborator`
  ADD CONSTRAINT `FK_bid_collaborator_bid` FOREIGN KEY (`bid_id`) REFERENCES `bid` (`id`) ON DELETE CASCADE;

ALTER TABLE `task_change_request`
  ADD CONSTRAINT `FK_change_request_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE CASCADE;

ALTER TABLE `task_change_log`
  ADD CONSTRAINT `FK_change_log_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_change_log_request` FOREIGN KEY (`request_id`) REFERENCES `task_change_request` (`id`) ON DELETE SET NULL;

-- ============================================================
-- 初始化数据（可选，演示用）
-- ============================================================
-- 实际部署时由 Phase 1 启动后跑 seed 脚本
