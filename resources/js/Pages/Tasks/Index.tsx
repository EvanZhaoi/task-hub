import AppLayout from '../../Layouts/AppLayout';

type TaskStatus = 'DRAFT' | 'OPEN' | 'PENDING_SELECTION' | 'ASSIGNED' | 'COMPLETED' | 'FAILED' | 'CANCELLED';
type TaskComplexity = 'LOW' | 'MEDIUM' | 'HIGH';

type SelectOption = {
    label: string;
    value: string;
};

type TaskListItem = {
    id: string;
    title: string;
    description: string;
    amountLabel: string;
    expectedDelivery: string | null;
    finalDelivery: string | null;
    biddingDeadline: string | null;
    displayStatus: TaskStatus;
    status: Exclude<TaskStatus, 'PENDING_SELECTION'>;
    assignmentType: 'BIDDING' | 'DIRECT';
    complexity: TaskComplexity;
    createdBy: string;
    createdByName: string;
    departmentName: string | null;
    paymentAccountName: string | null;
    createdAt: string | null;
    activeBidCount: number;
};

type PaginationMeta = {
    currentPage: number;
    from: number | null;
    lastPage: number;
    perPage: number;
    to: number | null;
    total: number;
};

type PaginatedTasks = {
    data: TaskListItem[];
    meta: PaginationMeta;
    links: {
        prev: string | null;
        next: string | null;
    };
};

type TaskFilters = {
    keyword: string;
    status: string;
    complexity: string;
};

type TaskIndexProps = {
    complexityOptions: SelectOption[];
    filters: TaskFilters;
    statusOptions: SelectOption[];
    tasks: PaginatedTasks;
};

const statusLabels: Record<TaskStatus, string> = {
    DRAFT: '草稿',
    OPEN: '招标中',
    PENDING_SELECTION: '待选标',
    ASSIGNED: '进行中',
    COMPLETED: '已完成',
    FAILED: '已流标',
    CANCELLED: '已取消',
};

const complexityLabels: Record<TaskComplexity, string> = {
    LOW: '简单',
    MEDIUM: '中等',
    HIGH: '复杂',
};

const statusClassNames: Record<TaskStatus, string> = {
    DRAFT: 'bg-gray-100 text-gray-600',
    OPEN: 'bg-blue-100 text-blue-800',
    PENDING_SELECTION: 'bg-amber-100 text-amber-800',
    ASSIGNED: 'bg-orange-100 text-orange-800',
    COMPLETED: 'bg-emerald-100 text-emerald-800',
    FAILED: 'bg-gray-200 text-gray-700',
    CANCELLED: 'bg-gray-50 text-gray-400 line-through',
};

const complexityClassNames: Record<TaskComplexity, string> = {
    LOW: 'bg-emerald-100 text-emerald-800',
    MEDIUM: 'bg-gray-100 text-gray-700',
    HIGH: 'bg-red-100 text-red-800',
};

function filterUrl(nextFilters: Partial<TaskFilters>, filters: TaskFilters): string {
    const params = new URLSearchParams();
    const merged = { ...filters, ...nextFilters };

    if (merged.keyword !== '') {
        params.set('keyword', merged.keyword);
    }

    if (merged.status !== 'ALL') {
        params.set('status', merged.status);
    }

    if (merged.complexity !== 'ALL') {
        params.set('complexity', merged.complexity);
    }

    const query = params.toString();

    return query === '' ? '/tasks' : `/tasks?${query}`;
}

export default function TaskIndex({ complexityOptions, filters, statusOptions, tasks }: TaskIndexProps) {
    return (
        <AppLayout
            activeNav="tasks"
            subtitle="所有公开任务 · 可投标 · 支持状态、复杂度和关键词筛选"
            title="任务大厅"
        >
            <div className="mb-4 rounded-lg border border-[#e5e7eb] bg-white p-4">
                <form action="/tasks" className="flex flex-col gap-3 lg:flex-row lg:items-center" method="GET">
                    <div className="relative min-w-0 flex-1">
                        <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#9ca3af]">
                            搜索
                        </span>
                        <input
                            className="h-10 w-full rounded-md border border-[#d1d5db] bg-white pl-12 pr-3 text-sm outline-none focus:border-[#5e6ad2] focus:ring-2 focus:ring-[#5e6ad2]/15"
                            defaultValue={filters.keyword}
                            name="keyword"
                            placeholder="输入标题或描述关键词"
                            type="search"
                        />
                    </div>

                    <select
                        className="h-10 rounded-md border border-[#d1d5db] bg-white px-3 text-sm text-[#374151] outline-none focus:border-[#5e6ad2] focus:ring-2 focus:ring-[#5e6ad2]/15"
                        defaultValue={filters.status}
                        name="status"
                    >
                        {statusOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                状态：{option.label}
                            </option>
                        ))}
                    </select>

                    <select
                        className="h-10 rounded-md border border-[#d1d5db] bg-white px-3 text-sm text-[#374151] outline-none focus:border-[#5e6ad2] focus:ring-2 focus:ring-[#5e6ad2]/15"
                        defaultValue={filters.complexity}
                        name="complexity"
                    >
                        {complexityOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                复杂度：{option.label}
                            </option>
                        ))}
                    </select>

                    <button
                        className="h-10 rounded-md bg-[#5e6ad2] px-4 text-sm font-medium text-white hover:bg-[#4f5bd5]"
                        type="submit"
                    >
                        查询
                    </button>
                    <a
                        className="flex h-10 items-center justify-center rounded-md border border-[#d1d5db] px-4 text-sm text-[#4b5563] hover:bg-[#f9fafb]"
                        href="/tasks"
                    >
                        重置
                    </a>
                </form>

                <div className="mt-3 flex flex-wrap gap-2">
                    {statusOptions.map((option) => {
                        const isActive = filters.status === option.value;

                        return (
                            <a
                                className={
                                    isActive
                                        ? 'rounded-full bg-[#f5f3ff] px-3 py-1 text-xs font-medium text-[#5e6ad2]'
                                        : 'rounded-full border border-[#e5e7eb] px-3 py-1 text-xs text-[#6e6e80] hover:border-[#c7d2fe] hover:text-[#4f46e5]'
                                }
                                href={filterUrl({ status: option.value }, filters)}
                                key={option.value}
                            >
                                {option.label}
                            </a>
                        );
                    })}
                </div>
            </div>

            <div className="mb-3 flex flex-wrap items-center justify-between gap-3 text-sm text-[#6e6e80]">
                <span>
                    显示 {tasks.meta.from ?? 0}-{tasks.meta.to ?? 0} / 共 {tasks.meta.total} 条
                </span>
                <span>
                    第 {tasks.meta.currentPage} / {tasks.meta.lastPage} 页
                </span>
            </div>

            <div className="space-y-3">
                {tasks.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-[#d1d5db] bg-white p-8 text-center">
                        <h2 className="text-base font-semibold">暂无符合条件的任务</h2>
                        <p className="mt-2 text-sm text-[#6e6e80]">可以调整关键词、状态或复杂度筛选条件后再试。</p>
                    </div>
                ) : (
                    tasks.data.map((task) => (
                        <article
                            className="rounded-lg border border-[#e5e7eb] bg-white p-5 transition hover:border-[#c7d2fe] hover:shadow-sm"
                            key={task.id}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="m-0 text-base font-semibold">{task.title}</h2>
                                        <span
                                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${statusClassNames[task.displayStatus]}`}
                                        >
                                            {statusLabels[task.displayStatus]}
                                        </span>
                                        <span
                                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${complexityClassNames[task.complexity]}`}
                                        >
                                            {complexityLabels[task.complexity]}
                                        </span>
                                    </div>
                                    <p className="mt-2 max-w-5xl text-sm leading-6 text-[#6e6e80]">
                                        {task.description || '暂无描述'}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <div className="text-base font-semibold">{task.amountLabel}</div>
                                    <div className="text-xs text-[#6e6e80]">
                                        {task.assignmentType === 'BIDDING' ? '招标选标' : '直接指派'}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-4 grid gap-3 text-sm text-[#6e6e80] md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <span className="block text-xs text-[#9ca3af]">交付日期</span>
                                    <strong className="font-semibold text-[#1a1a1a]">
                                        {task.finalDelivery ?? task.expectedDelivery ?? '-'}
                                    </strong>
                                </div>
                                <div>
                                    <span className="block text-xs text-[#9ca3af]">招标截止</span>
                                    <strong className="font-semibold text-[#1a1a1a]">
                                        {task.biddingDeadline ?? '-'}
                                    </strong>
                                </div>
                                <div>
                                    <span className="block text-xs text-[#9ca3af]">发布者</span>
                                    <strong className="font-semibold text-[#1a1a1a]">{task.createdByName}</strong>
                                    {task.departmentName ? ` · ${task.departmentName}` : ''}
                                </div>
                                <div>
                                    <span className="block text-xs text-[#9ca3af]">投标 / 预算</span>
                                    <strong className="font-semibold text-[#1a1a1a]">{task.activeBidCount}</strong>
                                    {task.paymentAccountName ? ` · ${task.paymentAccountName}` : ''}
                                </div>
                            </div>
                        </article>
                    ))
                )}
            </div>

            <div className="mt-5 flex items-center justify-between">
                {tasks.links.prev ? (
                    <a
                        className="rounded-md border border-[#d1d5db] bg-white px-4 py-2 text-sm text-[#4b5563] hover:bg-[#f9fafb]"
                        href={tasks.links.prev}
                    >
                        上一页
                    </a>
                ) : (
                    <span className="rounded-md border border-[#e5e7eb] bg-[#f9fafb] px-4 py-2 text-sm text-[#9ca3af]">
                        上一页
                    </span>
                )}

                {tasks.links.next ? (
                    <a
                        className="rounded-md border border-[#d1d5db] bg-white px-4 py-2 text-sm text-[#4b5563] hover:bg-[#f9fafb]"
                        href={tasks.links.next}
                    >
                        下一页
                    </a>
                ) : (
                    <span className="rounded-md border border-[#e5e7eb] bg-[#f9fafb] px-4 py-2 text-sm text-[#9ca3af]">
                        下一页
                    </span>
                )}
            </div>
        </AppLayout>
    );
}
