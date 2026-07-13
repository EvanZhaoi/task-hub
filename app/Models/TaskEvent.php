<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'related_type', 'related_id');
    }
}
