<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidMember extends Model
{
    protected $table = 'bid_member';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'bid_id',
        'user_id',
        'role',
    ];

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'bid_id');
    }
}
