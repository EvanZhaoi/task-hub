<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model
{
    protected $table = 'task';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'title',
        'description',
        'payment_account_id',
        'payment_account_snapshot',
        'budget',
        'final_amount',
        'expected_delivery',
        'final_delivery',
        'bidding_deadline',
        'status',
        'assignment_type',
        'complexity',
        'created_by',
        'created_by_snapshot',
        'assigned_bid_id',
        'primary_assignee_id',
        'version',
        'completed_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_account_snapshot' => 'array',
            'budget' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'expected_delivery' => 'date:Y-m-d',
            'final_delivery' => 'date:Y-m-d',
            'bidding_deadline' => 'datetime',
            'created_by_snapshot' => 'array',
            'version' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function assignedBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'assigned_bid_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'task_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(TaskAssignee::class, 'task_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(TaskDelivery::class, 'task_id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(TaskChangeRequest::class, 'task_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TaskEvent::class, 'task_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(AttachmentRef::class, 'owner', 'owner_type', 'owner_id');
    }
}
