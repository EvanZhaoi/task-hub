<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBidRequest;
use App\Models\Task;
use App\Services\CurrentUserService;
use App\Services\PlaceBidService;
use Illuminate\Http\RedirectResponse;

/**
 * 投标控制器。
 *
 * Controller 只处理 HTTP 层：接收请求、读取当前用户、调用业务 Service、返回重定向。
 * 具体投标事务和业务规则放在 PlaceBidService 中。
 */
class BidController extends Controller
{
    /**
     * 对指定任务提交投标。
     *
     * 投标入口位于任务详情页模态框内，提交成功后回到同一个详情页并显示成功提示。
     */
    public function store(
        StoreBidRequest $request,
        Task $task,
        CurrentUserService $currentUser,
        PlaceBidService $placeBid,
    ): RedirectResponse {
        $placeBid->execute(
            task: $task,
            validated: $request->validated(),
            attachments: $request->attachments(),
            user: $currentUser->user(),
        );

        return redirect()
            ->route('tasks.show', $task)
            ->with('success', '投标已提交。');
    }
}
