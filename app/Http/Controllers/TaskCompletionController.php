<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteTaskRequest;
use App\Models\Task;
use App\Services\CompleteTaskService;
use App\Services\CurrentUserService;
use App\Services\TaskReturnNavigation;
use Illuminate\Http\RedirectResponse;

/**
 * 处理发布者确认完成任务的 HTTP 请求。
 *
 * Controller 只负责接收已校验参数、取得当前用户、调用业务 Service 和返回页面；
 * 权限、状态流转、事务、事件和邮件都不在 Controller 中实现。
 */
class TaskCompletionController extends Controller
{
    /**
     * 将进行中的任务标记为已完成，并返回原任务详情来源。
     */
    public function __invoke(
        CompleteTaskRequest $request,
        Task $task,
        CurrentUserService $currentUser,
        CompleteTaskService $completeTask,
        TaskReturnNavigation $returnNavigation,
    ): RedirectResponse {
        $completeTask->execute(
            task: $task,
            user: $currentUser->user(),
            baseVersion: (int) $request->validated('version'),
            accessToken: $currentUser->accessToken(),
        );

        $routeParameters = ['task' => $task];
        $returnTo = $returnNavigation->safeUrl($request->validated('returnTo'));

        if ($returnTo !== null) {
            $routeParameters['returnTo'] = $returnTo;
        }

        return redirect()
            ->route('tasks.show', $routeParameters)
            ->with('success', '任务已完成。');
    }
}
