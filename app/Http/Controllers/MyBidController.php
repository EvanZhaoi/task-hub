<?php

namespace App\Http\Controllers;

use App\Integrations\Personnel\PersonnelClient;
use App\Integrations\Personnel\PersonnelException;
use App\Models\Bid;
use App\Services\CurrentUserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * “我的投标”页面控制器。
 *
 * OWNER 和 COLLABORATOR 都属于投标参与者，页面按投标记录而不是按任务聚合。
 */
class MyBidController extends Controller
{
    private const STATUSES = ['ACTIVE', 'ACCEPTED', 'LOST', 'WITHDRAWN'];

    /**
     * 查询当前用户参与的全部投标历史，最新提交排在最前。
     */
    public function __invoke(Request $request, CurrentUserService $currentUser, PersonnelClient $personnel): Response
    {
        $employeeNo = $currentUser->employeeNo();
        $filters = $this->filters($request);
        $query = Bid::query()
            ->whereHas('members', fn (Builder $query): Builder => $query->where('user_id', $employeeNo))
            ->with(['task', 'members' => fn ($query) => $query
                ->orderByRaw("role = 'OWNER' DESC")
                ->orderBy('id')]);

        if ($filters['keyword'] !== '') {
            $keyword = $this->likeKeyword($filters['keyword']);
            $query->where(fn (Builder $query): Builder => $query
                ->where('proposal', 'like', $keyword)
                ->orWhereHas('task', fn (Builder $query): Builder => $query->where('title', 'like', $keyword)));
        }

        if ($filters['status'] !== 'ALL') {
            $query->where('status', $filters['status']);
        }

        $paginator = $query->latest('created_at')->paginate(10)->withQueryString();
        $names = $this->personnelNames($personnel, $currentUser->accessToken());

        return Inertia::render('MyTasks/Bids', [
            'filters' => $filters,
            'bids' => $this->pagination($paginator, fn (Bid $bid): array => $this->item($bid, $employeeNo, $names)),
        ]);
    }

    /**
     * 规整关键词与投标状态筛选。
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
     * 把投标记录转换成投标工作台卡片数据。
     */
    private function item(Bid $bid, string $employeeNo, array $names): array
    {
        $currentMember = $bid->members->firstWhere('user_id', $employeeNo);

        return [
            'id' => (string) $bid->id,
            'taskId' => (string) $bid->task_id,
            'taskTitle' => $bid->task?->title ?? '任务已不可用',
            'taskStatus' => $bid->task?->status,
            'status' => $bid->status,
            'role' => $currentMember?->role ?? 'COLLABORATOR',
            'amountLabel' => '¥'.number_format((float) $bid->amount, 2),
            'deliveryDate' => $bid->delivery_date?->toDateString(),
            'proposal' => $bid->proposal,
            'revisionNo' => $bid->revision_no,
            'createdAt' => $bid->created_at?->format('Y-m-d H:i'),
            'members' => $bid->members->map(fn ($member): array => [
                'userId' => $member->user_id,
                'displayName' => $names[$member->user_id] ?? $member->user_id,
                'role' => $member->role,
            ])->values(),
        ];
    }

    /**
     * 读取缓存中的人员名称；人员服务不可用时退回展示工号，不阻断业务页。
     */
    private function personnelNames(PersonnelClient $personnel, string $accessToken): array
    {
        try {
            return collect($personnel->fetchAll($accessToken))
                ->filter(fn ($user): bool => $user->employeeNo() !== '')
                ->mapWithKeys(fn ($user): array => [$user->employeeNo() => $user->displayName() ?? $user->employeeNo()])
                ->all();
        } catch (PersonnelException $exception) {
            Log::warning('我的投标页面无法读取人员目录。', ['message' => $exception->getMessage()]);

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
