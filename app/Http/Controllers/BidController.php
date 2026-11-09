<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectBidRequest;
use App\Http\Requests\StoreBidRequest;
use App\Http\Requests\WithdrawBidRequest;
use App\Models\Bid;
use App\Models\Task;
use App\Services\CurrentUserService;
use App\Services\PlaceBidService;
use App\Services\SelectBidService;
use App\Services\TaskReturnNavigation;
use App\Services\WithdrawBidService;
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
        TaskReturnNavigation $returnNavigation,
    ): RedirectResponse {
        $placeBid->execute(
            task: $task,
            validated: $request->validated(),
            attachments: $request->attachments(),
            user: $currentUser->user(),
            accessToken: $currentUser->accessToken(),
        );

        $routeParameters = ['task' => $task];
        $returnTo = $returnNavigation->safeUrl($request->input('returnTo'));

        if ($returnTo !== null) {
            $routeParameters['returnTo'] = $returnTo;
        }

        return redirect()
            ->route('tasks.show', $routeParameters)
            ->with('success', '投标已提交。');
    }

    /**
     * 选定指定投标为中标方案。
     *
     * Controller 只传递路由模型和当前用户；完整状态流转、成员复制和事件写入由 SelectBidService 负责。
     */
    public function accept(
        SelectBidRequest $request,
        Task $task,
        Bid $bid,
        CurrentUserService $currentUser,
        SelectBidService $selectBid,
        TaskReturnNavigation $returnNavigation,
    ): RedirectResponse {
        $selectBid->execute(
            task: $task,
            bid: $bid,
            user: $currentUser->user(),
            accessToken: $currentUser->accessToken(),
        );

        $routeParameters = ['task' => $task];
        $returnTo = $returnNavigation->safeUrl($request->input('returnTo'));

        if ($returnTo !== null) {
            $routeParameters['returnTo'] = $returnTo;
        }

        return redirect()
            ->route('tasks.show', $routeParameters)
            ->with('success', '已选定中标方案，任务进入进行中。');
    }

    /**
     * 撤回指定投标。
     *
     * Controller 只负责读取当前用户和返回地址；OWNER 权限、截止时间、状态更新和事件记录
     * 全部由 WithdrawBidService 在同一个数据库事务中完成。
     */
    public function withdraw(
        WithdrawBidRequest $request,
        Task $task,
        Bid $bid,
        CurrentUserService $currentUser,
        WithdrawBidService $withdrawBid,
        TaskReturnNavigation $returnNavigation,
    ): RedirectResponse {
        $withdrawBid->execute(
            task: $task,
            bid: $bid,
            user: $currentUser->user(),
        );

        $routeParameters = ['task' => $task];
        $returnTo = $returnNavigation->safeUrl($request->input('returnTo'));

        if ($returnTo !== null) {
            $routeParameters['returnTo'] = $returnTo;
        }

        return redirect()
            ->route('tasks.show', $routeParameters)
            ->with('success', '投标已撤回，你可以在招标截止前重新投标。');
    }
}
