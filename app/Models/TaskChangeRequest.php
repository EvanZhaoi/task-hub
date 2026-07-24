<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 任务变更申请模型。
 *
 * CHANGE 用 old_value/new_value 保存金额、交期、描述的整体变更。
 * CANCEL 复用同一张表走双方确认流程，不额外创建取消申请表。
 */
class TaskChangeRequest extends Model
{
    protected $table = 'task_change_request';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'task_id',
        'request_no',
        'request_type',
        'old_value',
        'new_value',
        'reason',
        'base_task_version',
        'initiator_id',
        'approver_id',
        'status',
        'expires_at',
        'responded_at',
        'cancelled_at',
        'response_comment',
    ];

    /**
     * 定义变更申请字段的类型转换规则。
     *
     * old_value/new_value 转数组后，审批逻辑可以直接读取本次申请涉及的字段。
     */
    protected function casts(): array
    {
        return [
            'request_no' => 'integer',
            // JSON 字段只保存本次涉及的字段；未修改字段不写入 JSON。
            'old_value' => 'array',
            'new_value' => 'array',
            // 审批时必须比较 task.version 和 base_task_version，避免覆盖其他已生效修改。
            'base_task_version' => 'integer',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * 获取变更申请所属任务。
     *
     * 审批时需要通过该关联读取并锁定任务，校验 base_task_version。
     */
    public function task(): BelongsTo
    {
        // 每个变更申请都属于一个任务。
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * 获取变更申请关联的附件引用。
     *
     * 例如变更说明、补充材料等文件都通过 CHANGE_REQUEST 多态类型挂载。
     */
    public function attachments(): MorphMany
    {
        // 变更申请附件同样只保存外部附件 ID。
        return $this->morphMany(AttachmentRef::class, 'owner', 'owner_type', 'owner_id');
    }
}
