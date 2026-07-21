<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 任务模型。
 *
 * task 是 TaskHub 的核心聚合数据表，保存任务当前状态。
 * 历史动作放在 task_event，变更协商放在 task_change_request，不用事件反推当前状态。
 */
class Task extends Model
{
    protected $table = 'task';

    // 现有数据库结构使用 BIGINT 主键；当前阶段不让 Laravel 自动递增写入。
    public $incrementing = false;

    protected $keyType = 'int';

    // fillable 只描述“允许 Eloquent 批量赋值”的字段，不等同于页面可编辑字段。
    protected $fillable = [
        'id',
        'title',
        'description',
        'payment_account_id',
        'payment_account_snapshot',
        'budget',
        'final_amount',
        'expected_delivery',
        'final_delivery',
        'bidding_deadline',
        'status',
        'assignment_type',
        'complexity',
        'created_by',
        'created_by_snapshot',
        'assigned_bid_id',
        'primary_assignee_id',
        'version',
        'completed_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            // JSON 快照用于历史展示和审计，权限判断仍走实时外部人员接口。
            'payment_account_snapshot' => 'array',
            'budget' => 'decimal:2',
            'final_amount' => 'decimal:2',
            // 交付日期是 date；招标截止和完成时间是 timestamp/datetime 语义。
            'expected_delivery' => 'date:Y-m-d',
            'final_delivery' => 'date:Y-m-d',
            'bidding_deadline' => 'datetime',
            'created_by_snapshot' => 'array',
            // version 用于乐观锁，后续写业务时必须和数据库当前版本比较。
            'version' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function assignedBid(): BelongsTo
    {
        // BIDDING 模式下任务会记录中标 Bid；DIRECT 模式下该字段为空。
        return $this->belongsTo(Bid::class, 'assigned_bid_id');
    }

    public function bids(): HasMany
    {
        // 一个任务可以有多次投标历史，包括撤回后重新投标的记录。
        return $this->hasMany(Bid::class, 'task_id');
    }

    public function assignees(): HasMany
    {
        // 中标后会把 BidMember 复制到 TaskAssignee，执行阶段不再依赖 BidMember。
        return $this->hasMany(TaskAssignee::class, 'task_id');
    }

    public function deliveries(): HasMany
    {
        // 数据库支持多次交付；Phase 1 业务层暂时只开放一次交付。
        return $this->hasMany(TaskDelivery::class, 'task_id');
    }

    public function changeRequests(): HasMany
    {
        // 任务金额、交期、描述或取消协商统一记录在 task_change_request。
        return $this->hasMany(TaskChangeRequest::class, 'task_id');
    }

    public function events(): HasMany
    {
        // task_event 是任务详情时间线和审计记录，不用于重建 task 当前状态。
        return $this->hasMany(TaskEvent::class, 'task_id');
    }

    public function attachments(): MorphMany
    {
        // 发布任务时上传的附件通过多态关联挂到 TASK。
        return $this->morphMany(AttachmentRef::class, 'owner', 'owner_type', 'owner_id');
    }
}
