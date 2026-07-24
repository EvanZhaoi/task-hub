<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 任务事件模型。
 *
 * task_event 记录关键业务事件，用于任务详情时间线和审计。
 * 它不是消息队列、事件总线或 Event Sourcing，不承担状态重放职责。
 */
class TaskEvent extends Model
{
    protected $table = 'task_event';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'task_id',
        'event_type',
        'operator_id',
        'from_status',
        'to_status',
        'related_type',
        'related_id',
        'event_data',
        'remark',
    ];

    /**
     * 定义事件字段的类型转换规则。
     *
     * event_data 转数组后，可以直接读取事件发生时保存的业务快照。
     */
    protected function casts(): array
    {
        return [
            // event_data 保存事件发生时必要快照，例如主动流标原因、投标数量等。
            'event_data' => 'array',
        ];
    }

    /**
     * 获取事件所属任务。
     *
     * 任务详情时间线会按 task_id 查询这些事件。
     */
    public function task(): BelongsTo
    {
        // 每条事件都属于一个任务。
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * 获取事件关联的业务对象。
     *
     * related_type/related_id 可指向投标、交付或变更申请，用于从时间线跳转到具体记录。
     */
    public function related(): MorphTo
    {
        // related_type/related_id 可以指向 Bid、Delivery、ChangeRequest 等相关对象。
        return $this->morphTo(__FUNCTION__, 'related_type', 'related_id');
    }
}
