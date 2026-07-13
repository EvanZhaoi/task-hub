<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    protected function casts(): array
    {
        return [
            'delivery_no' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
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
