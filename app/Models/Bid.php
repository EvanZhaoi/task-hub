<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 投标模型。
 *
 * bid 表只保存一次投标的金额、交期、方案和状态。
 * 主投标人不放在 bid.bidder_id 中，而是统一放到 bid_member，避免成员模型出现双重来源。
 */
class Bid extends Model
{
    // 当前数据库表名不是 Laravel 默认的 bids，因此必须显式配置。
    protected $table = 'bid';

    // MVP 使用既有 schema.sql，不让 Eloquent 自动生成自增 ID。
    public $incrementing = false;

    protected $keyType = 'int';

    // active_key 由业务层生成，用唯一索引保证同一任务同一 OWNER 最多一条 ACTIVE 投标。
    protected $fillable = [
        'id',
        'task_id',
        'amount',
        'delivery_date',
        'proposal',
        'status',
        'revision_no',
        'active_key',
        'withdrawn_at',
    ];

    /**
     * 定义投标字段的类型转换规则。
     *
     * Eloquent 从数据库读取数据后，会按这里的规则把金额、日期和时间转换成更适合 PHP 使用的类型。
     */
    protected function casts(): array
    {
        return [
            // 金额统一 decimal(18,2)，Eloquent 以字符串形式保持精度，避免 float 精度误差。
            'amount' => 'decimal:2',
            // 交付日期只关心年月日，不带具体时间。
            'delivery_date' => 'date:Y-m-d',
            'revision_no' => 'integer',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * 获取该投标所属的任务。
     *
     * 用于从 Bid 反查 Task，例如选标事务中校验投标是否属于当前任务。
     */
    public function task(): BelongsTo
    {
        // 一个 Bid 必须属于一个 Task。
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * 获取该投标的全部成员。
     *
     * 主投标人和协作者都从这里读取，避免 bid 表和 bid_member 表重复保存主投标人。
     */
    public function members(): HasMany
    {
        // OWNER 和 COLLABORATOR 都在 bid_member 中表达，单人投标也会有一条 OWNER。
        return $this->hasMany(BidMember::class, 'bid_id');
    }

    /**
     * 获取该投标关联的附件引用。
     *
     * 真实文件在外部文件服务中，TaskHub 只保存 attachment_id。
     */
    public function attachments(): MorphMany
    {
        // 投标附件通过 AttachmentRef 多态关联，只保存外部附件 ID。
        return $this->morphMany(AttachmentRef::class, 'owner', 'owner_type', 'owner_id');
    }
}
