<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 附件引用模型。
 *
 * TaskHub 不保存真实文件内容，真实上传/下载由外部文件服务负责。
 * 本表只保存 attachment_id，并通过 owner_type + owner_id 挂到任务、投标、交付、变更申请等业务对象上。
 */
class AttachmentRef extends Model
{
    // 数据库表名是单数下划线形式，不符合 Eloquent 默认复数推断，所以显式指定。
    protected $table = 'attachment_ref';

    // 业务主键由数据库建表脚本或业务层生成，不使用 Eloquent 自增 ID。
    public $incrementing = false;

    // attachment_ref 表只有 created_at，没有 updated_at，因此关闭 Eloquent 对 updated_at 的自动维护。
    public const UPDATED_AT = null;

    // MySQL BIGINT UNSIGNED 在 Eloquent 中按 int 处理；对外展示时再按需要转字符串。
    protected $keyType = 'int';

    // 只开放当前阶段允许批量写入的字段，避免 Controller 误写入不存在或敏感字段。
    protected $fillable = [
        'id',
        'owner_type',
        'owner_id',
        'attachment_id',
        'uploaded_by',
    ];

    /**
     * 获取附件所属的业务对象。
     *
     * owner_type 决定关联到 Task、Bid、TaskDelivery 或 TaskChangeRequest，
     * 这样一张 attachment_ref 表就能支持多种业务附件。
     */
    public function owner(): MorphTo
    {
        // 多态关联：owner_type 保存业务对象类型，owner_id 保存对应业务对象 ID。
        // 由于 owner_id 可能指向不同表，数据库层不建外键，存在性由业务层校验。
        return $this->morphTo(__FUNCTION__, 'owner_type', 'owner_id');
    }
}
