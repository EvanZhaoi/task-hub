<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 投标成员模型。
 *
 * 主投标人和协作者都放在这张表中：
 * OWNER 表示主投标人，COLLABORATOR 表示协作成员。
 */
class BidMember extends Model
{
    protected $table = 'bid_member';

    // 主键由 schema.sql / 业务层控制，不启用 Eloquent 自增。
    public $incrementing = false;

    // 该表只记录成员创建，不维护 updated_at。
    public const UPDATED_AT = null;

    protected $keyType = 'int';

    // user_id 保存人员工号，不是本地 users 表外键。
    protected $fillable = [
        'id',
        'bid_id',
        'user_id',
        'role',
    ];

    /**
     * 获取成员所属的投标记录。
     *
     * 通过该关联可以从成员记录回到 Bid，查询投标金额、方案和状态。
     */
    public function bid(): BelongsTo
    {
        // 每个成员记录都属于某一次 Bid。
        return $this->belongsTo(Bid::class, 'bid_id');
    }
}
