<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 任务交付模型。
 *
 * 开发者正式提交交付时写入本表。
 * 数据库用 delivery_no 支持未来多次交付，Phase 1 业务层暂时只允许 delivery_no = 1。
 */
class TaskDelivery extends Model
{
    protected $table = 'task_delivery';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'task_id',
        'delivery_no',
        'submitted_by',
        'description',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_comment',
    ];

    /**
     * 定义交付字段的类型转换规则。
     *
     * submitted_at 和 reviewed_at 转换为日期时间对象，便于页面格式化和业务比较。
     */
    protected function casts(): array
    {
        return [
            // 同一任务内 delivery_no 递增，数据库通过 unique(task_id, delivery_no) 保证不重复。
            'delivery_no' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * 获取交付记录所属任务。
     *
     * 验收交付时需要通过该关联读取任务并更新任务状态。
     */
    public function task(): BelongsTo
    {
        // 交付必须归属于一个已指派的任务。
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * 获取交付内容关联的附件引用。
     *
     * 交付文件由外部文件服务保存，TaskHub 通过 attachment_ref 记录 ID。
     */
    public function attachments(): MorphMany
    {
        // 交付附件通过 DELIVERY owner_type 挂到 AttachmentRef。
        return $this->morphMany(AttachmentRef::class, 'owner', 'owner_type', 'owner_id');
    }
}
