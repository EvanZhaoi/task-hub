import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import type { TaskComplexity, TaskFilters, TaskIndexProps, TaskStatus } from '@/types/task';
import { urlWithQuery } from '@/utils/url';
import type { ComponentProps } from 'react';

// 页面展示文案集中在这里，避免 JSX 中散落大量枚举判断。
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

// 后端只传状态值，前端在这里把状态值映射为 Badge 的视觉类型。
const statusBadgeVariants: Record<TaskStatus, ComponentProps<typeof Badge>['variant']> = {
    DRAFT: 'default',
    OPEN: 'open',
    PENDING_SELECTION: 'pending',
    ASSIGNED: 'assigned',
    COMPLETED: 'completed',
    FAILED: 'failed',
    CANCELLED: 'cancelled',
};

// 复杂度也使用 Badge，但颜色含义和状态不同，因此单独维护映射。
const complexityBadgeVariants: Record<TaskComplexity, ComponentProps<typeof Badge>['variant']> = {
    LOW: 'completed',
    MEDIUM: 'default',
    HIGH: 'danger',
};

function filterUrl(nextFilters: Partial<TaskFilters>, filters: TaskFilters): string {
    // 快捷筛选要保留其它筛选条件，只替换用户当前点击的那一项。
    const merged = { ...filters, ...nextFilters };

    return urlWithQuery('/tasks', {
        keyword: merged.keyword,
        status: merged.status === 'ALL' ? null : merged.status,
        complexity: merged.complexity === 'ALL' ? null : merged.complexity,
    });
}

export default function TaskIndex({ complexityOptions, filters, statusOptions, tasks }: TaskIndexProps) {
    return (
        <AppLayout
            activeNav="tasks"
            subtitle="所有公开任务 · 可投标 · 支持状态、复杂度和关键词筛选"
            title="任务大厅"
        >
            <Card className="mb-4">
                <CardContent>
                    {/* GET 表单适合列表筛选：URL 可复制、可刷新、可用于浏览器前进后退。 */}
                    <form action="/tasks" className="flex flex-col gap-3 lg:flex-row lg:items-center" method="GET">
                        <div className="relative min-w-0 flex-1">
                            <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#9ca3af]">
                                搜索
                            </span>
                            <Input
                                className="pl-12"
                                defaultValue={filters.keyword}
                                name="keyword"
                                placeholder="输入标题或描述关键词"
                                type="search"
                            />
                        </div>

                        <NativeSelect defaultValue={filters.status} name="status">
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    状态：{option.label}
                                </option>
                            ))}
                        </NativeSelect>

                        <NativeSelect defaultValue={filters.complexity} name="complexity">
                            {complexityOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    复杂度：{option.label}
                                </option>
                            ))}
                        </NativeSelect>

                        <Button type="submit">查询</Button>
                        <Button asChild variant="outline">
                            <a href="/tasks">重置</a>
                        </Button>
                    </form>

                    <div className="mt-3 flex flex-wrap gap-2">
                        {statusOptions.map((option) => {
                            // 快捷筛选和上面的 select 使用同一套 filters，避免两个状态源不同步。
                            const isActive = filters.status === option.value;

                            return (
                                <a
                                    className={cn(
                                        'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                        isActive
                                            ? 'bg-[#f5f3ff] text-[#5e6ad2]'
                                            : 'border border-[#e5e7eb] text-[#6e6e80] hover:border-[#c7d2fe] hover:text-[#4f46e5]',
                                    )}
                                    href={filterUrl({ status: option.value }, filters)}
                                    key={option.value}
                                >
                                    {option.label}
                                </a>
                            );
                        })}
                    </div>
                </CardContent>
            </Card>

            <div className="mb-3 flex flex-wrap items-center justify-between gap-3 text-sm text-[#6e6e80]">
                {/* 分页元数据来自 Laravel paginator，后端已经转换成 camelCase。 */}
                <span>
                    显示 {tasks.meta.from ?? 0}-{tasks.meta.to ?? 0} / 共 {tasks.meta.total} 条
                </span>
                <span>
                    第 {tasks.meta.currentPage} / {tasks.meta.lastPage} 页
                </span>
            </div>

            <div className="space-y-3">
                {tasks.data.length === 0 ? (
                    // 空状态要占据列表区域，告诉用户可以通过调整筛选继续操作。
                    <Card className="border-dashed border-[#d1d5db]">
                        <CardContent className="p-8 text-center">
                            <h2 className="text-base font-semibold">暂无符合条件的任务</h2>
                            <p className="mt-2 text-sm text-[#6e6e80]">
                                可以调整关键词、状态或复杂度筛选条件后再试。
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    tasks.data.map((task) => (
                        // 单个任务卡片只做列表摘要；后续详情和操作会在卡片或模态框中继续扩展。
                        <Card
                            as="article"
                            className="p-5 transition hover:border-[#c7d2fe] hover:shadow-sm"
                            key={task.id}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="m-0 text-base font-semibold">{task.title}</h2>
                                        <Badge variant={statusBadgeVariants[task.displayStatus]}>
                                            {statusLabels[task.displayStatus]}
                                        </Badge>
                                        <Badge variant={complexityBadgeVariants[task.complexity]}>
                                            {complexityLabels[task.complexity]}
                                        </Badge>
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
                                {/* 关键业务字段用网格排列，方便用户快速扫描交期、截止、发布者和投标数。 */}
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
                        </Card>
                    ))
                )}
            </div>

            <div className="mt-5 flex items-center justify-between">
                {tasks.links.prev ? (
                    // asChild 让 a 标签保留链接行为，同时获得 Button 视觉样式。
                    <Button asChild variant="outline">
                        <a href={tasks.links.prev}>上一页</a>
                    </Button>
                ) : (
                    <Button disabled variant="muted">
                        上一页
                    </Button>
                )}

                {tasks.links.next ? (
                    // paginator 已经保留筛选 query string，直接使用后端给出的链接即可。
                    <Button asChild variant="outline">
                        <a href={tasks.links.next}>下一页</a>
                    </Button>
                ) : (
                    <Button disabled variant="muted">
                        下一页
                    </Button>
                )}
            </div>
        </AppLayout>
    );
}
