<?php

namespace App\Http\Controllers;

use App\Integrations\Personnel\PersonnelClient;
use App\Integrations\Personnel\PersonnelException;
use App\Models\TaskAssignee;
use App\Services\CurrentUserService;
use App\Services\RichTextSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * “执行任务”页面控制器。
 *
 * 页面只展示 task_assignee 中存在当前工号的正式执行任务，不把普通投标误认为执行关系。
 */
class AssignedTaskController extends Controller
{
    private const STATUSES = ['ASSIGNED', 'COMPLETED', 'CANCELLED'];

    /**
     * 查询当前用户的执行任务，未完成任务优先并按最终交期升序排列。
     */
    public function __invoke(
        Request $request,
        CurrentUserService $currentUser,
        PersonnelClient $personnel,
        RichTextSanitizer $richTextSanitizer,
    ): Response {
        $filters = $this->filters($request);
        $query = TaskAssignee::query()
            ->where('user_id', $currentUser->employeeNo())
            ->join('task', 'task.id', '=', 'task_assignee.task_id')
            ->select('task_assignee.*')
            ->with(['task.assignees' => fn ($query) => $query
                ->orderByRaw("role = 'OWNER' DESC")
                ->orderBy('id')]);

        if ($filters['keyword'] !== '') {
            $keyword = $this->likeKeyword($filters['keyword']);
            $query->where(fn (Builder $query): Builder => $query
                ->where('task.title', 'like', $keyword)
                ->orWhere('task.description', 'like', $keyword));
        }

        if ($filters['status'] !== 'ALL') {
            $query->where('task.status', $filters['status']);
        }

        $paginator = $query
            ->orderByRaw("CASE WHEN task.status = 'ASSIGNED' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN task.status = 'ASSIGNED' THEN task.final_delivery END ASC")
            ->orderByDesc('task.updated_at')
            ->paginate(10)
            ->withQueryString();
        $names = $this->personnelNames($personnel, $currentUser->accessToken());

        return Inertia::render('MyTasks/Assignments', [
            'filters' => $filters,
            'assignments' => $this->pagination(
                $paginator,
                fn (TaskAssignee $assignment): array => $this->item($assignment, $names, $richTextSanitizer),
            ),
        ]);
    }

    /**
     * 规整关键词和执行任务状态筛选。
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', 'ALL');

        return [
            'keyword' => trim((string) $request->query('keyword', '')),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'ALL',
        ];
    }

    /**
     * 把执行关系与任务数据转换成执行工作台卡片。
     */
    private function item(TaskAssignee $assignment, array $names, RichTextSanitizer $richTextSanitizer): array
    {
        $task = $assignment->task;

        return [
            'taskId' => (string) $assignment->task_id,
            'title' => $task?->title ?? '任务已不可用',
            'description' => $richTextSanitizer->clean($task?->description),
            'status' => $task?->status ?? 'CANCELLED',
            'role' => $assignment->role,
            'amountLabel' => '¥'.number_format((float) ($task?->final_amount ?? $task?->budget ?? 0), 2),
            'finalDelivery' => $task?->final_delivery?->toDateString(),
            'complexity' => $task?->complexity ?? 'MEDIUM',
            'isUrgent' => (bool) ($task?->is_urgent ?? false),
            'updatedAt' => $task?->updated_at?->format('Y-m-d H:i'),
            'assignees' => $task?->assignees->map(fn ($member): array => [
                'userId' => $member->user_id,
                'displayName' => $names[$member->user_id] ?? $member->user_id,
                'role' => $member->role,
            ])->values() ?? [],
        ];
    }

    /**
     * 读取人员名称；失败时保留工号作为稳定兜底。
     */
    private function personnelNames(PersonnelClient $personnel, string $accessToken): array
    {
        try {
            return collect($personnel->fetchAll($accessToken))
                ->filter(fn ($user): bool => $user->employeeNo() !== '')
                ->mapWithKeys(fn ($user): array => [$user->employeeNo() => $user->displayName() ?? $user->employeeNo()])
                ->all();
        } catch (PersonnelException $exception) {
            Log::warning('执行任务页面无法读取人员目录。', ['message' => $exception->getMessage()]);

            return [];
        }
    }

    /**
     * 转换分页结构并映射当前页记录。
     */
    private function pagination($paginator, callable $mapper): array
    {
        return [
            'data' => collect($paginator->items())->map($mapper)->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(), 'from' => $paginator->firstItem(),
                'lastPage' => $paginator->lastPage(), 'perPage' => $paginator->perPage(),
                'to' => $paginator->lastItem(), 'total' => $paginator->total(),
            ],
            'links' => ['prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()],
        ];
    }

    /**
     * 转义 LIKE 通配符。
     */
    private function likeKeyword(string $keyword): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword).'%';
    }
}
