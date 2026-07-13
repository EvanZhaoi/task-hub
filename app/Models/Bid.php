<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Bid extends Model
{
    protected $table = 'bid';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'task_id',
        'amount',
        'delivery_date',
        'proposal',
        'status',
        'revision_no',
        'active_key',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'delivery_date' => 'date:Y-m-d',
            'revision_no' => 'integer',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BidMember::class, 'bid_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(AttachmentRef::class, 'owner', 'owner_type', 'owner_id');
    }
}
