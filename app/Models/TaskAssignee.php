<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 任务执行成员模型。
 *
 * 任务被指派后，OWNER 和 COLLABORATOR 固化到 task_assignee。
 * Phase 1 执行期间不支持新增、删除或更换成员。
 */
class TaskAssignee extends Model
{
    protected $table = 'task_assignee';

    public $incrementing = false;

    // 成员关系创建后不在 MVP 中更新，因此表结构不需要 updated_at。
    public const UPDATED_AT = null;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'task_id',
        'user_id',
        'role',
    ];

    public function task(): BelongsTo
    {
        // 每条执行成员记录都归属于一个任务。
        return $this->belongsTo(Task::class, 'task_id');
    }
}
