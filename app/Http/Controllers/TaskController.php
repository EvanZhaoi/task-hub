<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    private const STATUSES = ['DRAFT', 'OPEN', 'PENDING_SELECTION', 'ASSIGNED', 'COMPLETED', 'FAILED', 'CANCELLED'];

    private const COMPLEXITIES = ['LOW', 'MEDIUM', 'HIGH'];

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $query = Task::query()
            ->select([
                'id',
                'title',
                'description',
                'budget',
                'final_amount',
                'expected_delivery',
                'final_delivery',
                'bidding_deadline',
                'status',
                'assignment_type',
                'complexity',
                'created_by',
                'created_by_snapshot',
                'payment_account_snapshot',
                'created_at',
            ])
            ->withCount([
                'bids as active_bid_count' => fn (Builder $query): Builder => $query->where('status', 'ACTIVE'),
            ]);

        $this->applyFilters($query, $filters);

        $paginator = $query
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $tasks = $paginator->through(fn (Task $task): array => $this->taskItem($task));

        return Inertia::render('Tasks/Index', [
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
            'complexityOptions' => $this->complexityOptions(),
            'tasks' => [
                'data' => $tasks->items(),
                'meta' => [
                    'currentPage' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'lastPage' => $paginator->lastPage(),
                    'perPage' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
        ]);
    }

    private function filters(Request $request): array
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $status = (string) $request->query('status', 'ALL');
        $complexity = (string) $request->query('complexity', 'ALL');

        return [
            'keyword' => $keyword,
            'status' => in_array($status, self::STATUSES, true) ? $status : 'ALL',
            'complexity' => in_array($complexity, self::COMPLEXITIES, true) ? $complexity : 'ALL',
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['keyword'] !== '') {
            $keyword = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters['keyword']).'%';

            $query->where(function (Builder $query) use ($keyword): void {
                $query
                    ->where('title', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword);
            });
        }

        if ($filters['complexity'] !== 'ALL') {
            $query->where('complexity', $filters['complexity']);
        }

        // “待选标”是页面派生状态，不是 task.status 的数据库枚举值。
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

    private function taskItem(Task $task): array
    {
        $createdBySnapshot = $task->created_by_snapshot ?? [];
        $paymentAccountSnapshot = $task->payment_account_snapshot ?? [];

        return [
            'id' => (string) $task->id,
            'title' => $task->title,
            'description' => Str::limit(strip_tags((string) $task->description), 120),
            'amountLabel' => $this->amountLabel($task),
            'expectedDelivery' => $task->expected_delivery?->toDateString(),
            'finalDelivery' => $task->final_delivery?->toDateString(),
            'biddingDeadline' => $task->bidding_deadline?->format('Y-m-d H:i'),
            'displayStatus' => $this->displayStatus($task),
            'status' => $task->status,
            'assignmentType' => $task->assignment_type,
            'complexity' => $task->complexity,
            'createdBy' => $task->created_by,
            'createdByName' => $createdBySnapshot['displayName'] ?? $task->created_by,
            'departmentName' => $createdBySnapshot['departmentName'] ?? null,
            'paymentAccountName' => $paymentAccountSnapshot['accountName'] ?? null,
            'createdAt' => $task->created_at?->format('Y-m-d H:i'),
            'activeBidCount' => (int) ($task->active_bid_count ?? 0),
        ];
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'ALL', 'label' => '全部'],
            ['value' => 'OPEN', 'label' => '招标中'],
            ['value' => 'PENDING_SELECTION', 'label' => '待选标'],
            ['value' => 'ASSIGNED', 'label' => '进行中'],
            ['value' => 'COMPLETED', 'label' => '已完成'],
            ['value' => 'FAILED', 'label' => '已流标'],
            ['value' => 'CANCELLED', 'label' => '已取消'],
        ];
    }

    private function complexityOptions(): array
    {
        return [
            ['value' => 'ALL', 'label' => '全部'],
            ['value' => 'LOW', 'label' => '简单'],
            ['value' => 'MEDIUM', 'label' => '中等'],
            ['value' => 'HIGH', 'label' => '复杂'],
        ];
    }

    private function displayStatus(Task $task): string
    {
        if (
            $task->status === 'OPEN'
            && $task->bidding_deadline !== null
            && $task->bidding_deadline->isPast()
            && (int) ($task->active_bid_count ?? 0) > 0
        ) {
            return 'PENDING_SELECTION';
        }

        return $task->status;
    }

    private function amountLabel(Task $task): string
    {
        $amount = $task->final_amount ?? $task->budget;

        return '¥'.number_format((float) $amount, 2);
    }
}
