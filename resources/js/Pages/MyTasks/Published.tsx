import { Link } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Gavel, Plus } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { MyTaskFilters } from '@/components/my-tasks/MyTaskFilters';
import { PaginationNav } from '@/components/my-tasks/PaginationNav';
import { RichTextViewer } from '@/components/common/RichTextViewer';
import { TaskAttributeBadges } from '@/components/tasks/TaskAttributeBadges';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { PublishedTasksProps } from '@/types/task';
import { taskDetailUrl } from '@/utils/task-navigation';

const statusOptions = [
    { label: '全部', value: 'ALL' },
    { label: '招标中', value: 'OPEN' },
    { label: '待选标', value: 'PENDING_SELECTION' },
    { label: '进行中', value: 'ASSIGNED' },
    { label: '已完成', value: 'COMPLETED' },
    { label: '已流标', value: 'FAILED' },
    { label: '已取消', value: 'CANCELLED' },
];

/**
 * 当前用户发布任务的管理页。
 *
 * 卡片重点突出任务当前状态、投标数量和下一步动作，不复制任务大厅的公共浏览布局。
 */
export default function PublishedTasks({ filters, tasks }: PublishedTasksProps) {
    return (
        <AppLayout
            activeNav="my-tasks"
            actions={(
                <Button asChild>
                    <Link href="/tasks">
                        <Plus className="mr-1.5 size-4" />
                        去任务大厅发布
                    </Link>
                </Button>
            )}
            subtitle="管理你发布的任务，查看投标进度并进入详情处理选标。"
            title="我发布的"
        >
            <div className="space-y-5">
                <MyTaskFilters
                    action="/my-tasks/published"
                    filters={filters}
                    placeholder="搜索任务标题或描述"
                    statusOptions={statusOptions}
                />

                {tasks.data.length === 0 ? (
                    <Card><CardContent className="p-10 text-center text-sm text-[#6e6e80]">没有找到符合条件的已发布任务。</CardContent></Card>
                ) : (
                    <div className="space-y-3">
                        {tasks.data.map((task) => (
                            <article key={task.id}>
                                <Card className="transition-colors hover:border-[#c7c9e8]">
                                    <CardContent className="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_180px_180px] lg:items-center">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-3">
                                                <Link className="truncate text-base font-semibold hover:text-[#5e6ad2]" href={taskDetailUrl(task.id)}>
                                                    {task.title}
                                                </Link>
                                                <TaskAttributeBadges complexity={task.complexity} displayStatus={task.displayStatus} isUrgent={task.isUrgent} />
                                            </div>
                                            <RichTextViewer className="mt-2 line-clamp-2" html={task.description} />
                                            <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#6e6e80]">
                                                <span>发布于 {task.createdAt ?? '-'}</span>
                                                <span>{task.assignmentType === 'BIDDING' ? '招标选标' : '直接指派'}</span>
                                            </div>
                                        </div>

                                        <div className="space-y-2 text-sm">
                                            <div className="flex items-center gap-2 text-[#6e6e80]"><CalendarDays className="size-4" />招标截止</div>
                                            <div className="font-medium text-[#374151]">{task.biddingDeadline ?? '无需招标'}</div>
                                            <div className="text-xs text-[#6e6e80]">{task.bidCount} 条投标，{task.activeBidCount} 条有效</div>
                                        </div>

                                        <div className="flex items-center justify-between gap-4 lg:block lg:text-right">
                                            <div>
                                                <div className="text-xs text-[#6e6e80]">{task.amountType === 'FINAL' ? '成交金额' : '任务预算'}</div>
                                                <div className="mt-1 text-lg font-semibold text-[#374151]">{task.amountLabel}</div>
                                            </div>
                                            <Button asChild className="mt-0 lg:mt-3" size="sm" variant={task.displayStatus === 'PENDING_SELECTION' ? 'primary' : 'outline'}>
                                                <Link href={taskDetailUrl(task.id)}>
                                                    {task.displayStatus === 'PENDING_SELECTION' ? <Gavel className="mr-1.5 size-4" /> : <ArrowRight className="mr-1.5 size-4" />}
                                                    {task.displayStatus === 'PENDING_SELECTION' ? '去选标' : '查看详情'}
                                                </Link>
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            </article>
                        ))}
                    </div>
                )}

                <PaginationNav links={tasks.links} meta={tasks.meta} />
            </div>
        </AppLayout>
    );
}
