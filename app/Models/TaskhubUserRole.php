<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskhubUserRole extends Model
{
    protected $table = 'taskhub_user_role';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'employee_no',
        'role',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
