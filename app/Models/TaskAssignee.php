<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAssignee extends Model
{
    protected $table = 'task_assignee';

    public $incrementing = false;

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
        return $this->belongsTo(Task::class, 'task_id');
    }
}
