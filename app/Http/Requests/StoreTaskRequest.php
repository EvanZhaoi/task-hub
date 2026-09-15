<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * 判断当前请求是否允许执行发布任务校验。
     *
     * 当前登录校验由 EnsureSsoAuthenticated 中间件负责，因此这里返回 true。
     * 后续如果增加发布权限矩阵，可以在这里或专门的权限服务中补充角色判断。
     */
    public function authorize(): bool
    {
        // 路由已经经过 EnsureSsoAuthenticated，中间件负责确认用户已登录。
        // 这里先允许所有已登录用户发布任务；更细的角色权限后续再统一补权限矩阵。
        return true;
    }

    /**
     * 返回发布任务表单的后端校验规则。
     *
     * 前端校验只能改善体验，不能作为可信边界；真正写库前必须经过这些 Laravel 校验规则。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 标题用于列表和详情首屏展示，限制长度与 schema.sql 中 task.title 一致。
            'title' => ['required', 'string', 'max:200'],
            // 当前描述按普通文本提交，保存到 LONGTEXT；未来接富文本编辑器时仍复用该字段。
            'description' => ['nullable', 'string', 'max:10000'],
            // 金额字段必须是 decimal 语义，后端用 numeric 校验，数据库用 DECIMAL(18,2) 保存。
            'budget' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            // 期望交付日期只关心自然日，不能早于今天。
            'expectedDelivery' => ['required', 'date', 'after_or_equal:today'],
            // 招标截止是具体时间，发布任务必须晚于当前时间。
            'biddingDeadline' => ['required', 'date', 'after:now'],
            // 枚举值必须和 database/schema.sql 的 CHECK 约束保持一致。
            'complexity' => ['required', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            // 付款账号来自外部系统；前端只提交 ID，名称和部门由后端调用外部接口获取。
            'paymentAccountId' => ['required', 'string', 'max:64'],
            // 附件已经由外部上传接口生成 ID 和名称。
            // 前端提交结构为 attachments: [{ id, name }]，后端只保存必要字段，不保存总部完整响应。
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*.id' => ['required', 'string', 'max:128'],
            'attachments.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * 解析发布任务表单中的附件引用。
     *
     * 前端选择文件后会先上传总部文件服务，再把总部返回的 id/name 放到 attachments。
     * 方法按 id 去重，避免同一附件重复写入 attachment_ref，同时保留第一次出现的文件名。
     *
     * @return list<array{id: string, name: string}>
     */
    public function attachments(): array
    {
        $attachments = $this->validated('attachments', []);

        if (! is_array($attachments)) {
            return [];
        }

        $uniqueAttachments = [];

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $id = trim((string) ($attachment['id'] ?? ''));
            $name = trim((string) ($attachment['name'] ?? ''));

            if ($id === '' || $name === '' || array_key_exists($id, $uniqueAttachments)) {
                continue;
            }

            $uniqueAttachments[$id] = [
                'id' => $id,
                'name' => $name,
            ];
        }

        return array_values($uniqueAttachments);
    }
}
