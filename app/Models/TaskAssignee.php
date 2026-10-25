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

    // task_assignee 只记录成员加入时间，数据库没有 updated_at。
    // 保持 timestamps 开启以自动写 created_at，仅关闭 updated_at。
    public $timestamps = true;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'task_id',
        'user_id',
        'role',
    ];

    /**
     * 将执行成员创建时间转换为 Carbon 日期时间对象。
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * 获取执行成员所属的任务。
     *
     * 用于从 task_assignee 回查任务当前状态、标题和交付要求。
     */
    public function task(): BelongsTo
    {
        // 每条执行成员记录都归属于一个任务。
        return $this->belongsTo(Task::class, 'task_id');
    }
}
