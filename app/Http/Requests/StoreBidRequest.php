<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBidRequest extends FormRequest
{
    /**
     * 判断当前请求是否允许执行投标校验。
     *
     * 登录状态由 EnsureSsoAuthenticated 中间件保证。
     * 当前阶段所有已登录用户都可以提交投标请求，真正的业务限制放到 PlaceBidService：
     * 例如任务是否仍在招标、是否是发布者本人、是否已有 ACTIVE 投标。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 返回投标表单的后端校验规则。
     *
     * 前端校验只负责体验；写入 bid / bid_member / attachment_ref 前必须经过这里的后端校验。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 投标金额写入 bid.amount，数据库是 DECIMAL(18,2)，不能使用 float 语义。
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            // 承诺交付日期是自然日，不能早于今天。
            'deliveryDate' => ['required', 'date', 'after_or_equal:today'],
            // proposal 是普通文本说明，暂不接富文本，避免投标弹窗过重。
            'proposal' => ['nullable', 'string', 'max:5000'],
            // 附件已经提前上传到总部文件服务，投标时只提交 id/name 引用。
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*.id' => ['required', 'string', 'max:128'],
            'attachments.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * 返回投标表单的中文校验提示。
     *
     * 所有用户可见的保存失败、字段校验提示都用中文，避免 Laravel 默认英文消息直接显示到页面。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => '请填写投标金额。',
            'amount.numeric' => '投标金额必须是数字。',
            'amount.min' => '投标金额不能小于 0。',
            'amount.max' => '投标金额过大，请检查后重新填写。',
            'deliveryDate.required' => '请选择承诺交付日期。',
            'deliveryDate.date' => '承诺交付日期格式不正确。',
            'deliveryDate.after_or_equal' => '承诺交付日期不能早于今天。',
            'proposal.string' => '投标说明必须是文本。',
            'proposal.max' => '投标说明不能超过 5000 个字符。',
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
     * 解析投标附件引用。
     *
     * 前端上传成功后会提交 attachments: [{ id, name }]。
     * 这里按 id 去重，避免同一个总部文件重复写入 attachment_ref。
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
