<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteTaskRequest extends FormRequest
{
    /**
     * 允许已通过 SSO 中间件的请求进入业务事务。
     *
     * 是否为发布者、任务是否仍为进行中都依赖锁定后的最新数据，
     * 因此不在 FormRequest 中使用可能过期的路由模型做权限判断。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // version 来自详情页当前快照，Service 会与锁定后的 Task.version 比较。
            'version' => ['required', 'integer', 'min:0'],
            // 返回地址还会经过 TaskReturnNavigation 白名单校验，不能直接用于外部跳转。
            'returnTo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * 返回面向业务用户的中文校验提示。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => '缺少任务版本，请刷新页面后重试。',
            'version.integer' => '任务版本格式不正确，请刷新页面后重试。',
            'version.min' => '任务版本格式不正确，请刷新页面后重试。',
            'returnTo.string' => '返回地址格式不正确。',
            'returnTo.max' => '返回地址过长。',
        ];
    }
}
