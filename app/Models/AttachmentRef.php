<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttachmentRef extends Model
{
    protected $table = 'attachment_ref';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'owner_type',
        'owner_id',
        'attachment_id',
        'uploaded_by',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'owner_type', 'owner_id');
    }
}
