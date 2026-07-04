-- ============================================================
-- TaskHub 数据库 Schema (MySQL 8.4)
-- 文档：docs/模块与数据库设计.md v0.7
-- 核心表：task / task_assignee / bid / bid_collaborator / attachment_ref / task_change_request / task_change_log
--
-- 设计约定：
-- 1. 用户、付款账号、附件文件本体都来自外部系统，本库只保存外部 ID。
-- 2. DATE 用于只关心自然日的交付日期；TIMESTAMP 用于具体事件发生时间。
-- 3. 软删字段统一使用 deleted_at；需要限制“未删除记录唯一”的场景使用生成列 + 唯一索引。
-- 4. task_change_log 是审计日志，不设置 deleted_at，不做软删。
-- 5. 外键集中在表创建后添加，避免 task.assigned_bid_id 与 bid.task_id 的循环引用顺序问题。
-- ============================================================

CREATE DATABASE IF NOT EXISTS `taskhub`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `taskhub`;

-- 重新初始化 schema 时需要按依赖关系删除表；这里临时关闭外键检查，
-- 删除完成后立即恢复，避免后续 DDL/DML 绕过约束。
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
--    任务生命周期的核心记录。招标模式下 assigned_bid_id 指向中标投标；
--    直接指名模式下 assigned_bid_id 为空，assigned_user_id 直接写被指名人。
-- ============================================================
CREATE TABLE `task` (
  `id`                 BIGINT UNSIGNED  NOT NULL             COMMENT '雪花 ID',
  `title`              VARCHAR(200)     NOT NULL             COMMENT '任务标题',
  `description`        LONGTEXT                              COMMENT '富文本描述（HTML）',
  `payment_account_id` VARCHAR(64)      NOT NULL             COMMENT '付款账号 ID（外部引用）',
  `budget`             DECIMAL(12, 2)   NOT NULL             COMMENT '预算金额',
  `final_amount`       DECIMAL(12, 2)                        COMMENT '最终成交金额（中标后填）',
  `expected_delivery`  DATE             NOT NULL             COMMENT '期望交期',
  `final_delivery`     DATE                                  COMMENT '最终交期',
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
  -- 任务大厅常用：按状态筛选并按创建时间倒序展示。
  KEY `IDX_status_created`       (`status`, `created_at` DESC),
  -- 个人中心“我发布的”。
  KEY `IDX_created_by_status`    (`created_by`, `status`),
  -- 个人中心“我负责的”；协作参与人通过 task_assignee 查询。
  KEY `IDX_assigned_user_status` (`assigned_user_id`, `status`),
  -- 老板视图按付款账号筛选。
  KEY `IDX_payment_account`      (`payment_account_id`, `status`),
  -- 任务大厅按复杂度筛选。
  KEY `IDX_complexity_status`    (`complexity`, `status`),
  -- MVP 标题 LIKE 搜索的辅助索引；描述全文搜索留二期。
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
--    这里不复用 bid_collaborator，是因为投标协作人是“候选方案成员”，
--    task_assignee 是“最终实际交付成员”，两者生命周期不同。
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
  -- MySQL 唯一索引允许多个 NULL，因此用生成列只约束未删除记录。
  -- 不能让生成列引用 task_id 再建立级联外键，所以 owner 唯一性用 flag + task_id 组合实现。
  `active_owner_flag` TINYINT GENERATED ALWAYS AS (
    CASE WHEN `role` = 0 AND `deleted_at` IS NULL THEN 1 ELSE NULL END
  ) STORED COMMENT '仅主负责人且未删除时用于唯一约束',
  PRIMARY KEY (`id`),
  -- 同一任务下，同一个用户只能有一条未删除参与记录。
  UNIQUE KEY `UNIQ_task_active_user` (`task_id`, `active_user_id`),
  -- 同一任务只能有一个未删除主负责人。
  UNIQUE KEY `UNIQ_task_active_owner` (`task_id`, `active_owner_flag`),
  KEY `IDX_task_id` (`task_id`),
  -- 查询“我参与的任务”，并可按 OWNER/COLLABORATOR 区分。
  KEY `IDX_user_role` (`user_id`, `role`),
  KEY `IDX_deleted` (`deleted_at`),
  CONSTRAINT `CHK_task_assignee_role` CHECK (`role` BETWEEN 0 AND 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务最终参与人';


-- ============================================================
-- 3. bid（投标表）
--    bidder_id 是主对接人；多人协作成员放在 bid_collaborator。
--    投标互相不可见由业务权限控制，数据库只负责记录和约束。
-- ============================================================
CREATE TABLE `bid` (
  `id`             BIGINT UNSIGNED NOT NULL             COMMENT '雪花 ID',
  `task_id`        BIGINT UNSIGNED NOT NULL             COMMENT '任务 ID',
  `bidder_id`      BIGINT UNSIGNED NOT NULL             COMMENT '投标主对接人 user_id',
  `amount`         DECIMAL(12, 2)  NOT NULL             COMMENT '投标金额',
  `delivery_date`  DATE            NOT NULL             COMMENT '承诺交期',
  `proposal`       TEXT                                 COMMENT '方案描述',
  `status`         TINYINT        NOT NULL DEFAULT 0    COMMENT '0=ACTIVE / 1=WITHDRAWN / 2=ACCEPTED / 3=LOST',
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     TIMESTAMP      NULL                  COMMENT '软删时间',
  `active_bidder_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` = 0 AND `deleted_at` IS NULL THEN `bidder_id` ELSE NULL END
  ) STORED COMMENT '仅 ACTIVE 且未删除时用于唯一约束',
  PRIMARY KEY (`id`),
  -- 任务详情加载投标列表。
  KEY `IDX_task_status` (`task_id`, `status`),
  -- 个人中心“我投标的”。
  KEY `IDX_bidder_status` (`bidder_id`, `status`),
  -- 同一任务同一主对接人只能有一个 ACTIVE 投标；撤回后允许重投。
  UNIQUE KEY `UNIQ_task_active_bidder` (`task_id`, `active_bidder_id`),
  KEY `IDX_deleted` (`deleted_at`),
  CONSTRAINT `CHK_bid_amount_non_negative` CHECK (`amount` >= 0),
  CONSTRAINT `CHK_bid_status` CHECK (`status` BETWEEN 0 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='投标表';


-- ============================================================
-- 4. bid_collaborator（投标协作人）
--    多人投标采用关系表而不是 JSON，便于查询、权限判断和唯一约束。
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
  -- 同一投标下，同一协作人只能出现一次未删除记录。
  UNIQUE KEY `UNIQ_bid_active_user` (`bid_id`, `active_user_id`),
  KEY `IDX_bid_id` (`bid_id`),
  -- 查询“我协作参与过的投标”。
  KEY `IDX_user_id` (`user_id`),
  KEY `IDX_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='投标协作人';


-- ============================================================
-- 5. attachment_ref（附件引用）
--    文件上传 / 下载由外部接口负责，本表只记录外部附件 ID。
--    支持任务和投标各自关联多个附件。
--    owner_type=0 时 owner_id=task.id；owner_type=1 时 owner_id=bid.id。
--    MySQL 无法对这种多态关联建立严格外键，完整性由业务层校验。
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
  -- 同一个任务/投标不能重复关联同一个未删除附件。
  UNIQUE KEY `UNIQ_owner_active_attachment` (`owner_type`, `owner_id`, `active_attachment_id`),
  -- 加载某个任务或投标的附件列表。
  KEY `IDX_owner` (`owner_type`, `owner_id`),
  -- 通过外部附件 ID 反查引用记录。
  KEY `IDX_attachment_id` (`attachment_id`),
  KEY `IDX_deleted` (`deleted_at`),
  CONSTRAINT `CHK_attachment_owner_type` CHECK (`owner_type` BETWEEN 0 AND 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='附件引用';


-- ============================================================
-- 6. task_change_request（任务变更请求 / 双方确认）
--    进行中任务的金额、交期、描述、延期、取消等协商先进入本表；
--    审批通过后再更新 task 并写入 task_change_log。
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
  -- 任务详情加载待处理/历史变更请求。
  KEY `IDX_task_status` (`task_id`, `status`),
  -- 查询“我待确认的变更”。
  KEY `IDX_approver_status` (`approver_id`, `status`),
  -- 同一任务同一变更类型只允许存在一个 PENDING 请求，避免并发协商冲突。
  UNIQUE KEY `UNIQ_task_pending_change` (`task_id`, `pending_change_type`),
  KEY `IDX_deleted` (`deleted_at`),
  CONSTRAINT `CHK_change_request_type` CHECK (`change_type` BETWEEN 0 AND 4),
  CONSTRAINT `CHK_change_request_status` CHECK (`status` BETWEEN 0 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务变更请求';


-- ============================================================
-- 7. task_change_log（任务变更记录 / 审计日志，不软删）
--    只记录已经生效的变更结果，用于任务详情审计展示。
--    这是 append-only 日志，不设置 updated_at / deleted_at。
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
  -- 任务详情按时间倒序加载变更记录。
  KEY `IDX_task_created` (`task_id`, `created_at` DESC),
  -- 追溯由哪个变更请求产生。
  KEY `IDX_request_id` (`request_id`),
  CONSTRAINT `CHK_change_log_type` CHECK (`change_type` BETWEEN 0 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务变更记录';


-- ============================================================
-- 跨表外键（处理 task <-> bid 的引用顺序）
-- 这些外键必须在所有表创建完成后统一添加：
-- - task.assigned_bid_id 引用 bid.id
-- - bid.task_id 又引用 task.id
-- 直接内联建表会形成创建顺序问题。
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
