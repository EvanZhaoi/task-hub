<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Integrations\Payment\PaymentAccount;
use App\Integrations\Payment\PaymentAccountClient;
use App\Integrations\Payment\PaymentAccountException;
use App\Integrations\Sso\SsoUser;
use App\Models\AttachmentRef;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Services\CurrentUserService;
use App\Services\SnowflakeId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 任务控制器。
 *
 * 当前只实现任务大厅只读列表。
 * 发布、投标、交付、变更等写入能力后续再按模块增加，不提前塞进本控制器。
 */
class TaskController extends Controller
{
    // 页面允许筛选的状态；PENDING_SELECTION 是派生展示状态，不保存进数据库。
    private const STATUSES = ['DRAFT', 'OPEN', 'PENDING_SELECTION', 'ASSIGNED', 'COMPLETED', 'FAILED', 'CANCELLED'];

    // 复杂度枚举来自 schema.sql，前端筛选和后端校验保持一致。
    private const COMPLEXITIES = ['LOW', 'MEDIUM', 'HIGH'];

    /**
     * 展示任务大厅列表页。
     *
     * 该方法读取筛选条件、查询任务分页数据、加载付款账号 Select 选项，
     * 最后通过 Inertia 返回 `resources/js/Pages/Tasks/Index.tsx` 页面。
     */
    public function index(Request $request, PaymentAccountClient $paymentAccounts): Response
    {
        // 先把用户输入规整成安全的筛选值，后续查询只使用规整后的 filters。
        $filters = $this->filters($request);

        $query = Task::query()
            ->select([
                // 列表页只选择展示需要的字段，避免把大 JSON 或暂时不用的字段都查出来。
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
                // active_bid_count 用于判断“待选标”和列表展示，不需要额外手写子查询。
                'bids as active_bid_count' => fn (Builder $query): Builder => $query->where('status', 'ACTIVE'),
            ]);

        $this->applyFilters($query, $filters);

        $paginator = $query
            // 任务大厅默认按发布时间倒序展示。
            ->latest('created_at')
            ->paginate(10)
            // 保留当前筛选条件，否则翻页后会丢失 keyword/status/complexity。
            ->withQueryString();

        // through() 只转换当前页数据，不改变分页结构。
        $tasks = $paginator->through(fn (Task $task): array => $this->taskItem($task));

        return Inertia::render('Tasks/Index', [
            'filters' => $filters,
            'paymentAccountOptions' => $this->paymentAccountOptions($paymentAccounts),
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

    /**
     * 发布一个招标任务。
     *
     * 该方法处理发布任务模态框提交的数据，在一个数据库事务中创建 task、附件引用和任务事件。
     * 付款账号名称不信任前端提交，必须通过 PaymentAccountClient 查询后保存历史快照。
     */
    public function store(
        StoreTaskRequest $request,
        CurrentUserService $currentUser,
        PaymentAccountClient $paymentAccounts,
        SnowflakeId $ids,
    ): RedirectResponse {
        $validated = $request->validated();
        $user = $currentUser->user();
        $employeeNo = $user->employeeNo();
        $attachmentIds = $request->attachmentIds();

        try {
            // 付款账号是外部主数据，前端只提交 ID；名称和部门快照必须由后端实时查询。
            // 外部查询放在事务外，避免数据库事务等待网络请求。
            $paymentAccount = $paymentAccounts->fetchById($validated['paymentAccountId']);
        } catch (PaymentAccountException $exception) {
            throw ValidationException::withMessages([
                'paymentAccountId' => $exception->getMessage(),
            ]);
        }

        DB::transaction(function () use ($attachmentIds, $employeeNo, $ids, $paymentAccount, $user, $validated): void {
            $taskId = $ids->next();

            // 当前发布入口只创建招标任务：发布后直接进入 OPEN，后续 DIRECT 指派单独做。
            $task = Task::query()->create([
                'id' => $taskId,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'payment_account_id' => $paymentAccount->accountId(),
                'payment_account_snapshot' => $this->paymentAccountSnapshot($paymentAccount),
                'budget' => $validated['budget'],
                'expected_delivery' => $validated['expectedDelivery'],
                'bidding_deadline' => $validated['biddingDeadline'],
                'status' => 'OPEN',
                'assignment_type' => 'BIDDING',
                'complexity' => $validated['complexity'],
                'created_by' => $employeeNo,
                'created_by_snapshot' => $this->createdBySnapshot($user),
                'version' => 0,
                'updated_by' => $employeeNo,
            ]);

            // TASK_CREATED 记录任务第一次进入系统时的快照。
            TaskEvent::query()->create([
                'id' => $ids->next(),
                'task_id' => $task->id,
                'event_type' => 'TASK_CREATED',
                'operator_id' => $employeeNo,
                'from_status' => null,
                'to_status' => 'OPEN',
                'event_data' => [
                    'title' => $task->title,
                    'budget' => $task->budget,
                    'complexity' => $task->complexity,
                    'assignmentType' => $task->assignment_type,
                ],
                'remark' => '发布者创建并发布任务',
            ]);

            // TASK_PUBLISHED 单独记录发布动作，后续如果支持草稿发布，可以复用同一事件类型。
            TaskEvent::query()->create([
                'id' => $ids->next(),
                'task_id' => $task->id,
                'event_type' => 'TASK_PUBLISHED',
                'operator_id' => $employeeNo,
                'from_status' => 'DRAFT',
                'to_status' => 'OPEN',
                'event_data' => [
                    'biddingDeadline' => $task->bidding_deadline?->toISOString(),
                    'attachmentCount' => count($attachmentIds),
                ],
                'remark' => '任务进入招标中',
            ]);

            if ($attachmentIds !== []) {
                $now = now();

                AttachmentRef::query()->insert(array_map(
                    fn (string $attachmentId): array => [
                        'id' => $ids->next(),
                        'owner_type' => 'TASK',
                        'owner_id' => $task->id,
                        'attachment_id' => $attachmentId,
                        'uploaded_by' => $employeeNo,
                        'created_at' => $now,
                    ],
                    $attachmentIds,
                ));
            }
        });

        return redirect()
            ->route('tasks.index')
            ->with('success', '任务已发布。');
    }

    /**
     * 从请求 query string 中解析并规整任务大厅筛选条件。
     *
     * 非法状态和复杂度会回退到 ALL，避免用户手动改 URL 导致异常查询。
     */
    private function filters(Request $request): array
    {
        // keyword 做 trim，但不过度清洗；LIKE 转义在 applyFilters 中处理。
        $keyword = trim((string) $request->query('keyword', ''));
        $status = (string) $request->query('status', 'ALL');
        $complexity = (string) $request->query('complexity', 'ALL');

        return [
            'keyword' => $keyword,
            // 非法枚举统一回到 ALL，避免用户改 URL 导致 SQL 查询异常。
            'status' => in_array($status, self::STATUSES, true) ? $status : 'ALL',
            'complexity' => in_array($complexity, self::COMPLEXITIES, true) ? $complexity : 'ALL',
        ];
    }

    /**
     * 把筛选条件应用到 Task 查询构造器。
     *
     * 该方法集中处理关键词、复杂度和状态筛选。
     * `PENDING_SELECTION` 是派生状态，不是数据库字段值，因此需要单独拼查询条件。
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['keyword'] !== '') {
            // 手动转义 LIKE 通配符，避免用户输入 % 或 _ 时意外扩大匹配范围。
            $keyword = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters['keyword']).'%';

            $query->where(function (Builder $query) use ($keyword): void {
                $query
                    ->where('title', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword);
            });
        }

        if ($filters['complexity'] !== 'ALL') {
            // complexity 是真实数据库字段，可以直接 where。
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
            // 除 PENDING_SELECTION 外，其它状态都是 task.status 的真实值。
            $query->where('status', $filters['status']);
        }
    }

    /**
     * 把 Task Model 转换成前端任务列表项数组。
     *
     * 前端不直接消费 Eloquent Model，而是接收已经格式化好的展示字段，
     * 这样可以避免前端重复处理 BIGINT、日期、金额和 JSON 快照。
     */
    private function taskItem(Task $task): array
    {
        // 快照字段可能为空，统一转成数组，避免前端展示时反复判断 null。
        $createdBySnapshot = $task->created_by_snapshot ?? [];
        $paymentAccountSnapshot = $task->payment_account_snapshot ?? [];

        return [
            // JS number 对大整数不安全，前端统一把业务 ID 当字符串展示和传递。
            'id' => (string) $task->id,
            'title' => $task->title,
            // 列表页只展示短描述，详情页再展示完整描述。
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

    /**
     * 返回任务大厅状态筛选选项。
     *
     * 选项由后端统一下发，保证前端筛选值、展示文案和后端查询逻辑保持一致。
     */
    private function statusOptions(): array
    {
        // 选项由后端下发，保证文案和后端筛选值同步。
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

    /**
     * 返回任务复杂度筛选选项。
     *
     * 复杂度枚举必须和 database/schema.sql 中 task.complexity 的 CHECK 约束一致。
     */
    private function complexityOptions(): array
    {
        // 复杂度选项同样由后端下发，未来改文案无需改前端枚举。
        return [
            ['value' => 'ALL', 'label' => '全部'],
            ['value' => 'LOW', 'label' => '简单'],
            ['value' => 'MEDIUM', 'label' => '中等'],
            ['value' => 'HIGH', 'label' => '复杂'],
        ];
    }

    /**
     * 获取发布任务弹窗使用的付款账号 Select 选项。
     *
     * 账号列表来自外部接口缓存；如果接口或缓存不可用，返回空数组，让页面显示“付款账号列表不可用”。
     */
    private function paymentAccountOptions(PaymentAccountClient $paymentAccounts): array
    {
        try {
            return array_map(
                fn (PaymentAccount $account): array => [
                    // value 是发布任务表单提交给后端的账号 ID。
                    'value' => $account->accountId(),
                    // label 是页面展示文案；没有账号名称时退回展示 ID，保证 Select 不出现空白项。
                    'label' => $account->accountName() === null
                        ? $account->accountId()
                        : "{$account->accountName()}（{$account->accountId()}）",
                    'accountName' => $account->accountName(),
                    'departmentName' => $account->departmentName(),
                ],
                $paymentAccounts->fetchAll(),
            );
        } catch (PaymentAccountException $exception) {
            // 账号列表用于页面选择；接口不可用时仍允许任务大厅打开，前端显示不可选择。
            // 真正发布时 store() 还会再次调用外部接口并返回 paymentAccountId 字段错误。
            return [];
        }
    }

    /**
     * 计算任务在列表页中的展示状态。
     *
     * 数据库没有 `PENDING_SELECTION` 状态；OPEN 任务已截止且存在 ACTIVE 投标时，
     * 页面派生展示为“待选标”，数据库仍保持 OPEN。
     */
    private function displayStatus(Task $task): string
    {
        // 截止且有 ACTIVE 投标时，数据库仍保持 OPEN；页面派生展示为“待选标”。
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

    /**
     * 格式化任务金额展示文案。
     *
     * 如果存在 final_amount，展示最终金额；否则展示预算金额，并统一加人民币符号和两位小数。
     */
    private function amountLabel(Task $task): string
    {
        // final_amount 表示协商后最终金额；没有最终金额时展示原预算。
        $amount = $task->final_amount ?? $task->budget;

        return '¥'.number_format((float) $amount, 2);
    }

    /**
     * 生成任务发布者历史快照。
     *
     * 快照只用于历史展示和审计，不能用于权限判断。
     * 权限判断仍应基于实时当前用户和后端角色规则。
     */
    private function createdBySnapshot(SsoUser $user): array
    {
        // 快照用于历史展示和审计；权限判断仍使用实时外部人员接口。
        return array_filter([
            'userId' => $user->employeeNo(),
            'employeeNo' => $user->employeeNo(),
            'displayName' => $user->displayName(),
            'departmentId' => $user->departmentId(),
            'departmentName' => $user->departmentName(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * 生成付款账号历史快照。
     *
     * 任务保存的是发布当时选择的付款账号展示信息，
     * 后续外部账号名称变化不会影响已有任务的历史展示。
     */
    private function paymentAccountSnapshot(PaymentAccount $paymentAccount): array
    {
        // 保留该方法用于表达业务语义：Task 保存的是付款账号历史快照，不是实时主数据。
        return $paymentAccount->toSnapshot();
    }
}
