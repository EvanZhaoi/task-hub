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

    // bid_member 保留 created_at，但数据库没有 updated_at。
    // timestamps 保持开启，让 Eloquent 创建记录时自动写入 created_at。
    public $timestamps = true;

    public const CREATED_AT = 'created_at';

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
     * 将数据库创建时间转换成 Carbon，读取成员加入时间时不需要手工解析字符串。
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

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
