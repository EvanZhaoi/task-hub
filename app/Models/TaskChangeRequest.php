<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    protected function casts(): array
    {
        return [
            'request_no' => 'integer',
            'old_value' => 'array',
            'new_value' => 'array',
            'base_task_version' => 'integer',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(AttachmentRef::class, 'owner', 'owner_type', 'owner_id');
    }
}
