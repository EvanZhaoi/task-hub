<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 撤回投标请求。
 *
 * 请求只携带详情页返回地址，不接受投标状态、撤回时间等业务字段。
 * 真正的权限和状态校验必须在数据库事务锁定记录后完成。
 */
class WithdrawBidRequest extends FormRequest
{
    /**
     * 允许已通过 SSO 中间件的请求进入撤回业务校验。
     *
     * 当前用户是否为 OWNER、任务是否截止等条件依赖最新数据库状态，
     * 因此不在 HTTP 授权阶段使用可能过期的路由模型判断。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 校验撤回请求中的页面导航参数。
     *
     * returnTo 最终还会经过 TaskReturnNavigation 白名单检查，不能直接作为外部跳转地址使用。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'returnTo' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
