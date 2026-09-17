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
     * 返回发布任务表单的中文校验提示。
     *
     * Laravel 默认校验消息是英文；这里按字段明确写中文，保证前端展示给用户的是中文提示。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => '请填写任务标题。',
            'title.string' => '任务标题必须是文本。',
            'title.max' => '任务标题不能超过 200 个字符。',
            'description.string' => '任务描述必须是文本。',
            'description.max' => '任务描述不能超过 10000 个字符。',
            'budget.required' => '请填写预算金额。',
            'budget.numeric' => '预算金额必须是数字。',
            'budget.min' => '预算金额不能小于 0。',
            'budget.max' => '预算金额过大，请检查后重新填写。',
            'expectedDelivery.required' => '请选择期望交付日期。',
            'expectedDelivery.date' => '期望交付日期格式不正确。',
            'expectedDelivery.after_or_equal' => '期望交付日期不能早于今天。',
            'biddingDeadline.required' => '请选择招标截止时间。',
            'biddingDeadline.date' => '招标截止时间格式不正确。',
            'biddingDeadline.after' => '招标截止时间必须晚于当前时间。',
            'complexity.required' => '请选择任务复杂度。',
            'complexity.string' => '任务复杂度格式不正确。',
            'complexity.in' => '任务复杂度只能选择简单、中等或复杂。',
            'paymentAccountId.required' => '请选择付款账号。',
            'paymentAccountId.string' => '付款账号格式不正确。',
            'paymentAccountId.max' => '付款账号编码不能超过 64 个字符。',
            'attachments.array' => '附件数据格式不正确。',
            'attachments.max' => '一次最多上传 20 个附件。',
            'attachments.*.id.required' => '附件缺少文件 ID，请重新上传。',
            'attachments.*.id.string' => '附件文件 ID 格式不正确。',
            'attachments.*.id.max' => '附件文件 ID 不能超过 128 个字符。',
            'attachments.*.name.required' => '附件缺少文件名称，请重新上传。',
            'attachments.*.name.string' => '附件文件名称格式不正确。',
            'attachments.*.name.max' => '附件文件名称不能超过 255 个字符。',
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
