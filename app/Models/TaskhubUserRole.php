<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TaskHub 本地角色模型。
 *
 * SSO 负责认证“当前是谁”，本表负责控制该员工在 TaskHub 里的业务角色。
 * 当前已确认角色只有 TOP，但数据库不限制死角色数量，便于后续扩展。
 */
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

    /**
     * 定义角色字段的类型转换规则。
     *
     * enabled 转换为布尔值后，角色服务可以直接按 true/false 判断是否启用。
     */
    protected function casts(): array
    {
        return [
            // enabled=false 表示暂时停用该角色，比删除历史配置更容易审计。
            'enabled' => 'boolean',
        ];
    }
}
