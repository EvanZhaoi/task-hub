-- ============================================================
-- TaskHub 数据库 Schema (MySQL 8)
-- 文档：docs/模块与数据库设计.md
-- MVP 核心表：
-- task / bid / bid_member / task_assignee / task_delivery /
-- attachment_ref / task_change_request / task_event
--
-- 设计约定：
-- 1. 人员、付款账号、附件文件本体都来自外部系统；人员引用统一保存工号。
-- 2. 金额统一使用 DECIMAL(18,2)，禁止使用 FLOAT / DOUBLE。
-- 3. DATE 用于只关心自然日的交付日期；TIMESTAMP 用于具体事件发生时间。
-- 4. 核心业务数据原则上不物理删除，通过状态和事件记录保留历史。
-- 5. 复杂跨表业务规则由业务层事务校验，不使用触发器。
-- 6. Task 保存当前状态，TaskEvent 保存历史事件，不用 TaskEvent 反向重建 Task。
-- ============================================================

CREATE DATABASE IF NOT EXISTS `taskhub`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `taskhub`;

-- 重新初始化 schema 时按依赖关系删除表；删除完成后立即恢复外键检查。
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `task_event`;
DROP TABLE IF EXISTS `task_change_request`;
DROP TABLE IF EXISTS `attachment_ref`;
DROP TABLE IF EXISTS `task_delivery`;
DROP TABLE IF EXISTS `task_assignee`;
DROP TABLE IF EXISTS `bid_member`;
DROP TABLE IF EXISTS `bid`;
DROP TABLE IF EXISTS `task`;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. task（任务主表）
--    保存任务当前状态和核心查询字段。
--    历史展示信息使用 JSON 快照；权限判断必须走实时外部人员接口。
-- ============================================================
CREATE TABLE `task` (
  `id`                       BIGINT UNSIGNED NOT NULL COMMENT '雪花 ID',
  `title`                    VARCHAR(200)    NOT NULL COMMENT '任务标题',
  `description`              LONGTEXT                 COMMENT '富文本任务描述（HTML）',
  `payment_account_id`       VARCHAR(64)     NOT NULL COMMENT '付款账号 ID（外部引用）',
  `payment_account_snapshot` JSON                     COMMENT '付款账号历史快照，仅用于展示和审计',
  `budget`                   DECIMAL(18, 2)  NOT NULL COMMENT '预算金额',
  `final_amount`             DECIMAL(18, 2)           COMMENT '最终成交金额',
  `expected_delivery`        DATE            NOT NULL COMMENT '期望交付日期',
  `final_delivery`           DATE                     COMMENT '最终交付日期',
  `bidding_deadline`         TIMESTAMP       NULL     COMMENT '招标截止时间',
  `status`                   VARCHAR(20)     NOT NULL DEFAULT 'DRAFT' COMMENT '任务状态：DRAFT/OPEN/ASSIGNED/COMPLETED/FAILED/CANCELLED',
  `assignment_type`          VARCHAR(20)     NOT NULL COMMENT '分配方式：BIDDING 招标选标 / DIRECT 直接指名',
  `complexity`               VARCHAR(20)     NOT NULL DEFAULT 'MEDIUM' COMMENT '复杂度：LOW/MEDIUM/HIGH',
  `created_by`               VARCHAR(32)     NOT NULL COMMENT '人员工号（外部人员接口标识）',
  `created_by_snapshot`      JSON                     COMMENT '发布者历史快照，仅用于展示和审计',
  `assigned_bid_id`          BIGINT UNSIGNED          COMMENT '中标投标 ID；DIRECT 模式为空',
  `primary_assignee_id`      VARCHAR(32)              COMMENT '人员工号（外部人员接口标识）',
  `version`                  BIGINT          NOT NULL DEFAULT 0 COMMENT '乐观锁版本号，业务更新时递增',
  `completed_at`             TIMESTAMP       NULL     COMMENT '任务完成时间',
  `created_at`               TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`               TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `updated_by`               VARCHAR(32)              COMMENT '人员工号（外部人员接口标识）',
  PRIMARY KEY (`id`),
  KEY `IDX_task_status_created` (`status`, `created_at` DESC),
  KEY `IDX_task_created_by_status_created` (`created_by`, `status`, `created_at` DESC),
  KEY `IDX_task_primary_assignee_status` (`primary_assignee_id`, `status`),
  KEY `IDX_task_payment_account_status` (`payment_account_id`, `status`),
  KEY `IDX_task_bidding_deadline_status` (`bidding_deadline`, `status`),
  KEY `IDX_task_complexity_status` (`complexity`, `status`),
  CONSTRAINT `CHK_task_budget_non_negative` CHECK (`budget` >= 0),
  CONSTRAINT `CHK_task_final_amount_non_negative` CHECK (`final_amount` IS NULL OR `final_amount` >= 0),
  CONSTRAINT `CHK_task_status` CHECK (`status` IN ('DRAFT', 'OPEN', 'ASSIGNED', 'COMPLETED', 'FAILED', 'CANCELLED')),
  CONSTRAINT `CHK_task_assignment_type` CHECK (`assignment_type` IN ('BIDDING', 'DIRECT')),
  CONSTRAINT `CHK_task_complexity` CHECK (`complexity` IN ('LOW', 'MEDIUM', 'HIGH'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务主表';

-- ============================================================
-- 2. bid（投标表）
--    保留投标历史；撤回后重投会生成新 bid 记录。
--    主投标人不在 bid 中重复保存，统一由 bid_member.role=OWNER 表示。
--    active_key 由业务层在 ACTIVE 状态下写入 task_id + OWNER 工号的唯一键。
-- ============================================================
CREATE TABLE `bid` (
  `id`            BIGINT UNSIGNED NOT NULL COMMENT '雪花 ID',
  `task_id`       BIGINT UNSIGNED NOT NULL COMMENT '任务 ID',
  `amount`        DECIMAL(18, 2)  NOT NULL COMMENT '投标金额',
  `delivery_date` DATE            NOT NULL COMMENT '承诺交付日期',
  `proposal`      TEXT                     COMMENT '投标方案说明',
  `status`        VARCHAR(20)     NOT NULL DEFAULT 'ACTIVE' COMMENT '投标状态：ACTIVE/WITHDRAWN/ACCEPTED/LOST',
  `revision_no`   INT             NOT NULL DEFAULT 1 COMMENT '同一任务同一 OWNER 的投标修订序号，由业务层递增',
  `active_key`    VARCHAR(160)             COMMENT 'ACTIVE 时唯一标识 task_id + OWNER 工号，如 10001:E12345；非 ACTIVE 时为 NULL',
  `withdrawn_at`  TIMESTAMP       NULL     COMMENT '撤回时间',
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_bid_active_key` (`active_key`),
  KEY `IDX_bid_task_status` (`task_id`, `status`),
  CONSTRAINT `CHK_bid_amount_non_negative` CHECK (`amount` >= 0),
  CONSTRAINT `CHK_bid_status` CHECK (`status` IN ('ACTIVE', 'WITHDRAWN', 'ACCEPTED', 'LOST'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='投标表';

-- ============================================================
-- 3. bid_member（投标成员表）
--    OWNER 是主投标人，COLLABORATOR 是协作者。
--    每个 Bid 必须且只能有一个 OWNER，由业务层保证。
-- ============================================================
CREATE TABLE `bid_member` (
  `id`         BIGINT UNSIGNED NOT NULL COMMENT '雪花 ID',
  `bid_id`     BIGINT UNSIGNED NOT NULL COMMENT '投标 ID',
  `user_id`    VARCHAR(32)     NOT NULL COMMENT '人员工号（外部人员接口标识）',
  `role`       VARCHAR(20)     NOT NULL COMMENT '成员角色：OWNER/COLLABORATOR',
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_bid_member_bid_user` (`bid_id`, `user_id`),
  KEY `IDX_bid_member_user_bid` (`user_id`, `bid_id`),
  CONSTRAINT `CHK_bid_member_role` CHECK (`role` IN ('OWNER', 'COLLABORATOR'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='投标成员表';

-- ============================================================
-- 4. task_assignee（任务执行成员表）
--    记录任务进入执行后实际参与交付的人。
--    DIRECT 模式写入被指名 OWNER；BIDDING 模式从中标 bid_member 复制。
--    MVP 执行期间不支持新增、删除或更换成员。
-- ============================================================
CREATE TABLE `task_assignee` (
  `id`         BIGINT UNSIGNED NOT NULL COMMENT '雪花 ID',
  `task_id`    BIGINT UNSIGNED NOT NULL COMMENT '任务 ID',
  `user_id`    VARCHAR(32)     NOT NULL COMMENT '人员工号（外部人员接口标识）',
  `role`       VARCHAR(20)     NOT NULL COMMENT '成员角色：OWNER/COLLABORATOR',
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_task_assignee_task_user` (`task_id`, `user_id`),
  KEY `IDX_task_assignee_user_task` (`user_id`, `task_id`),
  CONSTRAINT `CHK_task_assignee_role` CHECK (`role` IN ('OWNER', 'COLLABORATOR'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务执行成员表';

-- ============================================================
-- 5. task_delivery（任务交付表）
--    开发者可以多次提交交付；任务只有某次交付 ACCEPTED 后才能 COMPLETED。
--    交付附件通过 attachment_ref(owner_type='DELIVERY') 关联。
-- ============================================================
CREATE TABLE `task_delivery` (
  `id`             BIGINT UNSIGNED NOT NULL COMMENT '雪花 ID',
  `task_id`        BIGINT UNSIGNED NOT NULL COMMENT '任务 ID',
  `delivery_no`    INT             NOT NULL COMMENT '任务内交付序号，从 1 递增',
  `submitted_by`   VARCHAR(32)     NOT NULL COMMENT '人员工号（外部人员接口标识）',
  `description`    TEXT                     COMMENT '交付说明',
  `status`         VARCHAR(30)     NOT NULL DEFAULT 'SUBMITTED' COMMENT '交付状态：SUBMITTED/ACCEPTED/REVISION_REQUIRED/WITHDRAWN',
  `submitted_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
  `reviewed_by`    VARCHAR(32)              COMMENT '人员工号（外部人员接口标识）',
  `reviewed_at`    TIMESTAMP       NULL     COMMENT '评审时间',
  `review_comment` VARCHAR(1000)            COMMENT '评审意见',
  `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_task_delivery_no` (`task_id`, `delivery_no`),
  KEY `IDX_task_delivery_task_status` (`task_id`, `status`),
  KEY `IDX_task_delivery_submitted_by_at` (`submitted_by`, `submitted_at` DESC),
  CONSTRAINT `CHK_task_delivery_no_positive` CHECK (`delivery_no` > 0),
  CONSTRAINT `CHK_task_delivery_status` CHECK (`status` IN ('SUBMITTED', 'ACCEPTED', 'REVISION_REQUIRED', 'WITHDRAWN'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务交付表';

-- ============================================================
-- 6. attachment_ref（附件引用表）
--    文件上传 / 下载由外部文件服务负责，本表只记录外部 attachment_id。
--    owner_type + owner_id 为多态关联，不建立数据库外键，由业务层校验对象存在。
-- ============================================================
CREATE TABLE `attachment_ref` (
  `id`            BIGINT UNSIGNED NOT NULL COMMENT '雪花 ID',
  `owner_type`    VARCHAR(30)     NOT NULL COMMENT '所属对象类型：TASK/BID/DELIVERY/CHANGE_REQUEST',
  `owner_id`      BIGINT UNSIGNED NOT NULL COMMENT '所属对象 ID',
  `attachment_id` VARCHAR(128)    NOT NULL COMMENT '外部附件 ID',
  `uploaded_by`   VARCHAR(32)     NOT NULL COMMENT '人员工号（外部人员接口标识）',
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_attachment_owner_file` (`owner_type`, `owner_id`, `attachment_id`),
  KEY `IDX_attachment_owner` (`owner_type`, `owner_id`),
  CONSTRAINT `CHK_attachment_owner_type` CHECK (`owner_type` IN ('TASK', 'BID', 'DELIVERY', 'CHANGE_REQUEST'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='附件引用表';

-- ============================================================
-- 7. task_change_request（任务变更请求表）
--    保存双方协商审批过程。审批通过时需要校验 task.version 是否仍等于 base_task_version。
--    old_value/new_value 使用固定 camelCase JSON 结构，不能任意写入。
-- ============================================================
CREATE TABLE `task_change_request` (
  `id`                BIGINT UNSIGNED NOT NULL COMMENT '雪花 ID',
  `task_id`           BIGINT UNSIGNED NOT NULL COMMENT '任务 ID',
  `request_no`        INT             NOT NULL COMMENT '任务内变更请求编号，从 1 递增',
  `request_type`      VARCHAR(20)     NOT NULL COMMENT '请求类型：CHANGE/CANCEL',
  `old_value`         JSON                     COMMENT '变更前值，camelCase JSON',
  `new_value`         JSON                     COMMENT '变更后值，camelCase JSON',
  `reason`            VARCHAR(500)             COMMENT '变更原因',
  `base_task_version` BIGINT          NOT NULL COMMENT '发起变更时的 task.version',
  `initiator_id`      VARCHAR(32)     NOT NULL COMMENT '人员工号（外部人员接口标识）',
  `approver_id`       VARCHAR(32)     NOT NULL COMMENT '人员工号（外部人员接口标识）',
  `status`            VARCHAR(20)     NOT NULL DEFAULT 'PENDING' COMMENT '请求状态：PENDING/APPROVED/REJECTED/CANCELLED',
  `expires_at`        TIMESTAMP       NULL     COMMENT '过期时间；MVP 暂不自动过期',
  `responded_at`      TIMESTAMP       NULL     COMMENT '确认/拒绝时间',
  `cancelled_at`      TIMESTAMP       NULL     COMMENT '取消时间',
  `response_comment`  VARCHAR(1000)            COMMENT '确认/拒绝/取消时的备注',
  `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_change_request_task_no` (`task_id`, `request_no`),
  KEY `IDX_change_request_task_status_created` (`task_id`, `status`, `created_at` DESC),
  KEY `IDX_change_request_approver_status_created` (`approver_id`, `status`, `created_at` DESC),
  CONSTRAINT `CHK_change_request_no_positive` CHECK (`request_no` > 0),
  CONSTRAINT `CHK_change_request_type` CHECK (`request_type` IN ('CHANGE', 'CANCEL')),
  CONSTRAINT `CHK_change_request_status` CHECK (`status` IN ('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务变更请求表';

-- ============================================================
-- 8. task_event（任务事件表）
--    记录任务详情时间线和业务审计。不是事件溯源、消息队列、事件总线或 Outbox，
--    不用于反向重建 Task 当前状态。
-- ============================================================
CREATE TABLE `task_event` (
  `id`           BIGINT UNSIGNED NOT NULL COMMENT '雪花 ID',
  `task_id`      BIGINT UNSIGNED NOT NULL COMMENT '任务 ID',
  `event_type`   VARCHAR(40)     NOT NULL COMMENT '事件类型',
  `operator_id`  VARCHAR(32)              COMMENT '人员工号（外部人员接口标识）；系统事件可为空',
  `from_status`  VARCHAR(20)              COMMENT '变更前任务状态',
  `to_status`    VARCHAR(20)              COMMENT '变更后任务状态',
  `related_type` VARCHAR(30)              COMMENT '关联对象类型，如 BID/DELIVERY/CHANGE_REQUEST',
  `related_id`   BIGINT UNSIGNED          COMMENT '关联对象 ID',
  `event_data`   JSON                     COMMENT '事件快照数据',
  `remark`       VARCHAR(500)             COMMENT '备注',
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `IDX_task_event_task_created` (`task_id`, `created_at` DESC),
  KEY `IDX_task_event_operator_created` (`operator_id`, `created_at` DESC),
  CONSTRAINT `CHK_task_event_type` CHECK (`event_type` IN (
    'TASK_CREATED', 'TASK_PUBLISHED', 'BID_SUBMITTED', 'BID_WITHDRAWN',
    'BID_ACCEPTED', 'TASK_ASSIGNED', 'CHANGE_REQUESTED', 'CHANGE_APPROVED',
    'CHANGE_REJECTED', 'DELIVERY_SUBMITTED', 'DELIVERY_ACCEPTED',
    'REVISION_REQUIRED', 'TASK_COMPLETED', 'TASK_CANCELLED', 'TASK_FAILED',
    'BIDDING_DEADLINE_EXTENDED'
  )),
  CONSTRAINT `CHK_task_event_from_status` CHECK (`from_status` IS NULL OR `from_status` IN ('DRAFT', 'OPEN', 'ASSIGNED', 'COMPLETED', 'FAILED', 'CANCELLED')),
  CONSTRAINT `CHK_task_event_to_status` CHECK (`to_status` IS NULL OR `to_status` IN ('DRAFT', 'OPEN', 'ASSIGNED', 'COMPLETED', 'FAILED', 'CANCELLED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='任务事件表';

-- ============================================================
-- 内部外键
-- 说明：
-- 1. attachment_ref 使用多态关联，不建立数据库外键。
-- 2. 用户、付款账号、附件文件本体来自外部系统，不建立本地外键。
-- 3. task.assigned_bid_id 与 bid.task_id 存在引用顺序问题，因此统一后置添加外键。
-- ============================================================
ALTER TABLE `task`
  ADD CONSTRAINT `FK_task_assigned_bid` FOREIGN KEY (`assigned_bid_id`) REFERENCES `bid` (`id`) ON DELETE RESTRICT;

ALTER TABLE `bid`
  ADD CONSTRAINT `FK_bid_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE RESTRICT;

ALTER TABLE `bid_member`
  ADD CONSTRAINT `FK_bid_member_bid` FOREIGN KEY (`bid_id`) REFERENCES `bid` (`id`) ON DELETE RESTRICT;

ALTER TABLE `task_assignee`
  ADD CONSTRAINT `FK_task_assignee_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE RESTRICT;

ALTER TABLE `task_delivery`
  ADD CONSTRAINT `FK_task_delivery_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE RESTRICT;

ALTER TABLE `task_change_request`
  ADD CONSTRAINT `FK_change_request_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE RESTRICT;

ALTER TABLE `task_event`
  ADD CONSTRAINT `FK_task_event_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`) ON DELETE RESTRICT;

-- ============================================================
-- 初始化数据（可选，演示用）
-- ============================================================
-- 实际部署时由 Phase 1 启动后跑 seed 脚本
