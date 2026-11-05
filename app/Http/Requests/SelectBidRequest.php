<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SelectBidRequest extends FormRequest
{
    /**
     * 允许已通过 SSO 中间件的请求进入业务校验。
     *
     * 是否为任务发布者、任务和投标状态是否合法都依赖锁定后的数据库数据，
     * 因此统一由 SelectBidService 在事务内校验，避免 authorize() 使用过期模型。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 选标动作不接收可编辑业务字段。
     *
     * task 和 bid 都来自路由参数，最终金额、交期和成员必须从数据库中的目标 Bid 读取，
     * 不能接受前端重复提交这些值，否则可能出现被篡改的数据。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
