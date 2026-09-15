<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 发布任务附件上传请求校验。
 *
 * 该请求只校验浏览器上传到 TaskHub 后端的文件本身。
 * 真正上传到总部文件服务由 FileUploadClient 负责。
 */
class UploadAttachmentRequest extends FormRequest
{
    /**
     * 判断当前请求是否允许上传附件。
     *
     * 登录校验由 EnsureSsoAuthenticated 中间件负责，因此这里允许所有已登录用户上传。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 返回附件上传表单校验规则。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 当前 MVP 先限制单文件 20MB，避免误传过大文件拖慢发布任务流程。
            // 文件类型暂不限制，由总部文件服务和后续产品规则共同决定。
            'file' => ['required', 'file', 'max:20480'],
        ];
    }
}
