-- ============================================================
-- TaskHub 数据库 Schema (MySQL 8.4)
-- 文档：docs/模块与数据库设计.md v0.6
-- 4 张核心表：task / bid / task_attachment / task_change_log
-- ============================================================

CREATE DATABASE IF NOT EXISTS `taskhub`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `taskhub`;

-- ============================================================
-- 1. task（任务主表）
-- ============================================================
DROP TABLE IF EXISTS `task`;
CREATE TABLE `task` (
  `id`                 BIGINT UNSIGNED  NOT NULL             COMMENT '雪花 ID',
  `title`              VARCHAR(200)     NOT NULL             COMMENT '任务标题',
  `description`        LONGTEXT                              COMMENT '富文本描述（HTML）',
  `payment_account_id` VARCHAR(64)      NOT NULL             COMMENT '付款账号 ID（外部引用）',
  `budget`             DECIMAL(12, 2)   NOT NULL             COMMENT '预算金额',
  `final_amount`       DECIMAL(12, 2)                       COMMENT '最终成交金额（中标后填）',
  `expected_delivery`  DATE             NOT NULL             COMMENT '期望交期',
  `final_delivery`     DATE                                  COMMENT '最终交期',
  `bidding_deadline`   DATETIME                              COMMENT '招标截止时间（流标判定依据）',
  `status`             TINYINT          NOT NULL DEFAULT 0   COMMENT '0=DRAFT / 1=OPEN / 2=ASSIGNED / 3=COMPLETED / 4=FAILED / 5=CANCELLED',
  `is_direct`          TINYINT(1)       NOT NULL DEFAULT 0   COMMENT '是否直接指名 0=否 1=是',
  `created_by`         BIGINT UNSIGNED  NOT NULL             COMMENT '发布者 user_id',
  `assigned_bid_id`    BIGINT UNSIGNED                       COMMENT '中标投标 ID（冗余）',
  `created_at`         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`         BIGINT UNSIGNED                       COMMENT '最后修改人',
  `deleted_at`         DATETIME                              COMMENT '软删时间',
  PRIMARY KEY (`id`),
  KEY `IDX_status_created`    (`status`, `created_at`),
  KEY `IDX_created_by_status`  (`created_by`, `status`),
  KEY `IDX_payment_account`    (`payment_account_id`, `status`),
  KEY `IDX_deleted`            (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务主表';


-- ============================================================
-- 2. bid（投标表）
-- ============================================================
DROP TABLE IF EXISTS `bid`;
CREATE TABLE `bid` (
  `id`             BIGINT UNSIGNED  NOT NULL             COMMENT '雪花 ID',
  `task_id`        BIGINT UNSIGNED  NOT NULL             COMMENT '任务 ID',
  `bidder_id`      BIGINT UNSIGNED  NOT NULL             COMMENT '投标者 user_id',
  `amount`         DECIMAL(12, 2)  NOT NULL             COMMENT '投标金额',
  `delivery_date`  DATE            NOT NULL             COMMENT '承诺交期',
  `proposal`       TEXT                                  COMMENT '方案描述',
  `status`         TINYINT         NOT NULL DEFAULT 0   COMMENT '0=ACTIVE / 1=WITHDRAWN / 2=ACCEPTED / 3=LOST',
  `collaborators`  JSON                                 COMMENT '协作成员 user_id 列表 [u1, u2]',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME                             COMMENT '软删时间',
  PRIMARY KEY (`id`),
  KEY `IDX_task_status`     (`task_id`, `status`),
  KEY `IDX_bidder_status`    (`bidder_id`, `status`),
  UNIQUE KEY `UNIQ_task_bidder_active` (`task_id`, `bidder_id`, `status`),
  KEY `IDX_deleted`         (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='投标表';


-- ============================================================
-- 3. task_attachment（任务附件）
-- ============================================================
DROP TABLE IF EXISTS `task_attachment`;
CREATE TABLE `task_attachment` (
  `id`           BIGINT UNSIGNED  NOT NULL             COMMENT '雪花 ID',
  `task_id`      BIGINT UNSIGNED  NOT NULL             COMMENT '任务 ID',
  `file_name`    VARCHAR(255)    NOT NULL             COMMENT '原始文件名',
  `file_key`     VARCHAR(255)    NOT NULL             COMMENT '存储 key（StorageService 抽象）',
  `file_size`    BIGINT UNSIGNED NOT NULL             COMMENT '文件大小（字节）',
  `mime_type`    VARCHAR(100)                        COMMENT 'MIME 类型',
  `uploaded_by`  BIGINT UNSIGNED NOT NULL             COMMENT '上传者 user_id',
  `uploaded_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`   DATETIME                             COMMENT '软删时间',
  PRIMARY KEY (`id`),
  KEY `IDX_task_id`     (`task_id`),
  KEY `IDX_deleted`     (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务附件';


-- ============================================================
-- 4. task_change_log（任务变更记录）
-- ============================================================
DROP TABLE IF EXISTS `task_change_log`;
CREATE TABLE `task_change_log` (
  `id`           BIGINT UNSIGNED  NOT NULL             COMMENT '雪花 ID',
  `task_id`      BIGINT UNSIGNED  NOT NULL             COMMENT '任务 ID',
  `change_type`  TINYINT         NOT NULL             COMMENT '0=AMOUNT / 1=DELIVERY / 2=DESCRIPTION / 3=EXTENSION / 4=CANCEL',
  `old_value`    JSON                                 COMMENT '变更前值',
  `new_value`    JSON                                 COMMENT '变更后值',
  `reason`       VARCHAR(500)                        COMMENT '原因',
  `agreed_by`    JSON                                 COMMENT '双方 user_id 列表 [u1, u2]',
  `created_by`   BIGINT UNSIGNED                     COMMENT '发起人',
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `IDX_task_created`  (`task_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务变更记录';


-- ============================================================
-- 初始化数据（可选，演示用）
-- ============================================================
-- 实际部署时由 Phase 1 启动后跑 seed 脚本
