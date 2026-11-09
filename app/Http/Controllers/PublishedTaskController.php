<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\CurrentUserService;
use App\Services\RichTextSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * “我发布的”页面控制器。
 *
 * 这是只读查询入口，不包含事务或状态流转，因此直接使用 Eloquent 完成页面查询。
 */
class PublishedTaskController extends Controller
{
    private const STATUSES = ['OPEN', 'PENDING_SELECTION', 'ASSIGNED', 'COMPLETED', 'FAILED', 'CANCELLED'];

    /**
     * 查询当前登录人发布的任务，并按创建时间倒序分页展示。
     */
    public function __invoke(
        Request $request,
        CurrentUserService $currentUser,
        RichTextSanitizer $richTextSanitizer,
    ): Response {
        $filters = $this->filters($request);
        $query = Task::query()
            ->where('created_by', $currentUser->employeeNo())
            ->withCount([
                'bids as active_bid_count' => fn (Builder $query): Builder => $query->where('status', 'ACTIVE'),
                // 已撤回记录只在详情历史中展示，不计入“我发布的”列表投标数量。
                'bids as bid_count' => fn (Builder $query): Builder => $query->where('status', '!=', 'WITHDRAWN'),
            ]);

        $this->applyFilters($query, $filters);

        $paginator = $query->latest('created_at')->paginate(10)->withQueryString();

        return Inertia::render('MyTasks/Published', [
            'filters' => $filters,
            'tasks' => $this->pagination(
                $paginator,
                fn (Task $task): array => $this->item($task, $richTextSanitizer),
            ),
        ]);
    }

    /**
     * 规整页面筛选条件，非法状态统一回退为 ALL。
     *
     * @return array{keyword: string, status: string}
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
     * 应用标题、描述和状态筛选；待选标继续使用派生查询条件，不写入数据库状态。
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['keyword'] !== '') {
            $keyword = $this->likeKeyword($filters['keyword']);
            $query->where(fn (Builder $query): Builder => $query
                ->where('title', 'like', $keyword)
                ->orWhere('description', 'like', $keyword));
        }

        if ($filters['status'] === 'PENDING_SELECTION') {
            $query
                ->where('status', 'OPEN')
                ->where('bidding_deadline', '<=', now())
                ->whereHas('bids', fn (Builder $query): Builder => $query->where('status', 'ACTIVE'));

            return;
        }

        if ($filters['status'] !== 'ALL') {
            $query->where('status', $filters['status']);
        }
    }

    /**
     * 把任务转换为“我发布的”管理卡片需要的数据。
     */
    private function item(Task $task, RichTextSanitizer $richTextSanitizer): array
    {
        return [
            'id' => (string) $task->id,
            'title' => $task->title,
            'description' => $richTextSanitizer->clean($task->description),
            'amountLabel' => '¥'.number_format((float) ($task->final_amount ?? $task->budget), 2),
            'amountType' => $task->final_amount === null ? 'BUDGET' : 'FINAL',
            'displayStatus' => $this->displayStatus($task),
            'status' => $task->status,
            'assignmentType' => $task->assignment_type,
            'complexity' => $task->complexity,
            'isUrgent' => $task->is_urgent,
            'expectedDelivery' => $task->expected_delivery?->toDateString(),
            'finalDelivery' => $task->final_delivery?->toDateString(),
            'biddingDeadline' => $task->bidding_deadline?->format('Y-m-d H:i'),
            'createdAt' => $task->created_at?->format('Y-m-d H:i'),
            'activeBidCount' => (int) ($task->active_bid_count ?? 0),
            'bidCount' => (int) ($task->bid_count ?? 0),
        ];
    }

    /**
     * 计算任务页面展示状态。
     */
    private function displayStatus(Task $task): string
    {
        if ($task->status === 'OPEN' && $task->bidding_deadline?->isPast() && (int) $task->active_bid_count > 0) {
            return 'PENDING_SELECTION';
        }

        return $task->status;
    }

    /**
     * 转换分页器，避免把 Eloquent 对象直接发送到浏览器。
     */
    private function pagination($paginator, callable $mapper): array
    {
        return [
            'data' => collect($paginator->items())->map($mapper)->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => ['prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()],
        ];
    }

    /**
     * 转义 LIKE 通配符，让百分号和下划线按普通字符查询。
     */
    private function likeKeyword(string $keyword): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword).'%';
    }
}
