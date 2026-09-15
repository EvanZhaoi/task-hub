<?php

namespace App\Integrations\FileOperation;

/**
 * 总部文件上传成功后的附件引用对象。
 *
 * TaskHub 不保存文件内容，只保存总部返回的 data.id。
 */
final readonly class UploadedFileRef
{
    /**
     * 创建上传文件引用。
     *
     * @param  array<string, mixed>  $raw  总部上传接口原始响应，保留用于排查字段变化。
     */
    public function __construct(
        private string $id,
        private string $name,
        private array $raw = [],
    ) {}

    /**
     * 从总部上传接口完整响应中解析附件 ID。
     *
     * 当前确认的真实字段是 data.id，不是 fileId。
     */
    public static function fromPayload(array $payload, string $originalName): self
    {
        $id = $payload['data']['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new FileUploadException('File upload response does not contain data.id.');
        }

        return new self(
            id: $id,
            name: $originalName,
            raw: $payload,
        );
    }

    /**
     * 获取总部文件服务返回的附件 ID。
     *
     * 发布任务时最终写入 attachment_ref.attachment_id。
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * 获取用户本次上传时的原始文件名。
     *
     * 前端仅用于列表展示，不写入 TaskHub 当前数据库结构。
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * 转换为上传接口返回给 React 的 JSON。
     *
     * 前端只需要 id 和 name，不接收总部完整响应。
     *
     * @return array{id: string, name: string}
     */
    public function toResponsePayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    /**
     * 获取总部原始响应。
     *
     * 仅用于测试或排查接口字段变化，业务代码不要依赖 raw。
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
