<?php

namespace App\Integrations\FileOperation;

/**
 * 总部文件上传成功后的附件引用对象。
 *
 * TaskHub 不保存文件内容，只保存总部返回的 data.id 和 data.name。
 * id 用于后续下载/预览，name 用于任务详情直接展示文件名。
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
     * 从总部上传接口完整响应中解析附件 ID 和文件名。
     *
     * 当前确认的真实结构是：
     * {
     *   "code": "",
     *   "data": {
     *     "id": "...",
     *     "name": "..."
     *   },
     *   "msg": "",
     *   "timestamp": 0
     * }
     *
     * 这里必须读取 data.id 和 data.name，不读取 fileId，也不使用浏览器原始文件名兜底。
     */
    public static function fromPayload(array $payload): self
    {
        $id = $payload['data']['id'] ?? null;
        $name = $payload['data']['name'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new FileUploadException('File upload response does not contain data.id.');
        }

        if (! is_string($name) || $name === '') {
            throw new FileUploadException('File upload response does not contain data.name.');
        }

        return new self(
            id: $id,
            name: $name,
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
     * 获取总部文件服务返回的附件名称。
     *
     * 发布任务时最终写入 attachment_ref.attachment_name，任务详情可以直接展示。
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
