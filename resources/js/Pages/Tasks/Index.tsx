import { router } from '@inertiajs/react';

type TaskListItem = {
    id: string;
    title: string;
    description: string;
    amountLabel: string;
    expectedDelivery: string | null;
    finalDelivery: string | null;
    biddingDeadline: string | null;
    status: 'DRAFT' | 'OPEN' | 'ASSIGNED' | 'COMPLETED' | 'FAILED' | 'CANCELLED';
    assignmentType: 'BIDDING' | 'DIRECT';
    complexity: 'LOW' | 'MEDIUM' | 'HIGH';
    createdBy: string;
    createdByName: string;
    departmentName: string | null;
    paymentAccountName: string | null;
};

type TaskIndexProps = {
    tasks: TaskListItem[];
};

const statusLabels: Record<TaskListItem['status'], string> = {
    DRAFT: '草稿',
    OPEN: '招标中',
    ASSIGNED: '进行中',
    COMPLETED: '已完成',
    FAILED: '已流标',
    CANCELLED: '已取消',
};

const complexityLabels: Record<TaskListItem['complexity'], string> = {
    LOW: '简单',
    MEDIUM: '中等',
    HIGH: '复杂',
};

const statusClassNames: Record<TaskListItem['status'], string> = {
    DRAFT: 'bg-gray-100 text-gray-600',
    OPEN: 'bg-blue-100 text-blue-800',
    ASSIGNED: 'bg-orange-100 text-orange-800',
    COMPLETED: 'bg-emerald-100 text-emerald-800',
    FAILED: 'bg-gray-200 text-gray-700',
    CANCELLED: 'bg-gray-50 text-gray-400 line-through',
};

const complexityClassNames: Record<TaskListItem['complexity'], string> = {
    LOW: 'bg-emerald-100 text-emerald-800',
    MEDIUM: 'bg-gray-100 text-gray-700',
    HIGH: 'bg-red-100 text-red-800',
};

export default function TaskIndex({ tasks }: TaskIndexProps) {
    return (
        <main className="min-h-screen bg-[#fafafa] text-[#1a1a1a]">
            <header className="sticky top-0 z-10 border-b border-[#ebebeb] bg-white px-6 py-3">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-[#5e6ad2] text-sm font-bold text-white">
                            T
                        </div>
                        <span className="text-sm font-semibold">TaskHub</span>
                        <span className="rounded-full border border-[#fde68a] bg-[#fef3c7] px-2 py-0.5 text-xs text-[#92400e]">
                            学习闭环
                        </span>
                    </div>
                    <nav className="flex items-center gap-1 text-sm">
                        <a className="rounded-md px-3 py-1.5 text-[#6e6e80] hover:bg-[#fafafa]" href="/">
                            首页
                        </a>
                        <span className="rounded-md bg-[#f5f3ff] px-3 py-1.5 font-medium text-[#5e6ad2]">
                            任务列表
                        </span>
                        <button
                            className="rounded-md px-3 py-1.5 text-[#6e6e80] hover:bg-[#fafafa]"
                            onClick={() => router.post('/logout')}
                            type="button"
                        >
                            退出
                        </button>
                    </nav>
                </div>
            </header>

            <section className="mx-auto max-w-6xl px-6 py-8">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="m-0 text-2xl font-bold">任务列表</h1>
                        <p className="mt-1 text-sm text-[#6e6e80]">
                            从 MySQL 读取 task 表，通过 Laravel Controller 和 Inertia 传给 React。
                        </p>
                    </div>
                    <div className="rounded-md border border-[#ebebeb] bg-white px-3 py-2 text-sm text-[#6e6e80]">
                        共 {tasks.length} 条
                    </div>
                </div>

                <div className="space-y-3">
                    {tasks.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-[#d1d5db] bg-white p-8 text-center">
                            <h2 className="text-base font-semibold">暂无任务数据</h2>
                            <p className="mt-2 text-sm text-[#6e6e80]">
                                页面已经跑通；当前数据库 task 表为空，所以没有可展示的任务。
                            </p>
                        </div>
                    ) : (
                        tasks.map((task) => (
                            <article
                                className="rounded-lg border border-[#ebebeb] bg-white p-5 transition hover:border-[#d1d5db] hover:shadow-sm"
                                key={task.id}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h2 className="m-0 text-base font-semibold">{task.title}</h2>
                                        <p className="mt-2 max-w-4xl text-sm leading-6 text-[#6e6e80]">
                                            {task.description || '暂无描述'}
                                        </p>
                                    </div>
                                    <span
                                        className={`rounded-full px-2.5 py-1 text-xs font-medium ${statusClassNames[task.status]}`}
                                    >
                                        {statusLabels[task.status]}
                                    </span>
                                </div>

                                <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-[#6e6e80]">
                                    <span>
                                        金额 <strong className="font-semibold text-[#1a1a1a]">{task.amountLabel}</strong>
                                    </span>
                                    <span>
                                        交付{' '}
                                        <strong className="font-semibold text-[#1a1a1a]">
                                            {task.finalDelivery ?? task.expectedDelivery ?? '-'}
                                        </strong>
                                    </span>
                                    <span>
                                        招标截止{' '}
                                        <strong className="font-semibold text-[#1a1a1a]">
                                            {task.biddingDeadline ?? '-'}
                                        </strong>
                                    </span>
                                    <span
                                        className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${complexityClassNames[task.complexity]}`}
                                    >
                                        {complexityLabels[task.complexity]}
                                    </span>
                                    <span>
                                        发布者{' '}
                                        <strong className="font-semibold text-[#1a1a1a]">
                                            {task.createdByName}
                                        </strong>
                                        {task.departmentName ? ` · ${task.departmentName}` : ''}
                                    </span>
                                    {task.paymentAccountName ? <span>{task.paymentAccountName}</span> : null}
                                </div>
                            </article>
                        ))
                    )}
                </div>
            </section>
        </main>
    );
}
