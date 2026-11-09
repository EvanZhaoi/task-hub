import { Link } from '@inertiajs/react';
import { CalendarCheck, Users } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { RichTextViewer } from '@/components/common/RichTextViewer';
import { MyTaskFilters } from '@/components/my-tasks/MyTaskFilters';
import { PaginationNav } from '@/components/common/PaginationNav';
import { TaskAttributeBadges } from '@/components/tasks/TaskAttributeBadges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { AssignedTasksProps } from '@/types/task';
import { taskDetailUrl } from '@/utils/task-navigation';

const statusOptions = [
    { label: '全部', value: 'ALL' },
    { label: '进行中', value: 'ASSIGNED' },
    { label: '已完成', value: 'COMPLETED' },
    { label: '已取消', value: 'CANCELLED' },
];

/**
 * 当前用户已经正式进入 task_assignee 的执行任务页面。
 *
 * 未完成任务由后端排在前面，卡片重点展示最终交期、执行角色和正式团队。
 */
export default function AssignedTasks({ assignments, filters }: AssignedTasksProps) {
    return (
        <AppLayout activeNav="my-tasks" subtitle="集中查看你作为负责人或协作成员正在执行及已经完成的任务。" title="执行任务">
            <div className="space-y-5">
                <MyTaskFilters action="/my-tasks/assignments" filters={filters} placeholder="搜索任务标题或描述" statusOptions={statusOptions} />

                {assignments.data.length === 0 ? (
                    <Card><CardContent className="p-10 text-center text-sm text-[#6e6e80]">没有找到符合条件的执行任务。</CardContent></Card>
                ) : (
                    <div className="space-y-3">
                        {assignments.data.map((assignment) => (
                            <article key={assignment.taskId}>
                                <Card className="transition-colors hover:border-[#c7c9e8] hover:shadow-sm">
                                    <CardContent className="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(220px,280px)_170px] lg:items-center">
                                        {/* 左侧突出任务本身和当前用户在执行团队中的角色。 */}
                                        <section className="min-w-0 lg:pr-2">
                                            <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                <Link
                                                    className="truncate text-base font-semibold text-[#1f2937] hover:text-[#5e6ad2]"
                                                    href={taskDetailUrl(assignment.taskId)}
                                                >
                                                    {assignment.title}
                                                </Link>
                                                <Badge className="shrink-0" variant="default">
                                                    {assignment.role === 'OWNER' ? '任务负责人' : '协作成员'}
                                                </Badge>
                                            </div>
                                            <TaskAttributeBadges
                                                className="mt-2"
                                                complexity={assignment.complexity}
                                                displayStatus={assignment.status}
                                                isUrgent={assignment.isUrgent}
                                            />
                                            <RichTextViewer
                                                className="mt-2 line-clamp-2 max-w-none text-sm leading-5 [&_*]:m-0"
                                                html={assignment.description}
                                            />
                                            <div className="mt-3 text-xs text-[#9ca3af]">
                                                最近更新：{assignment.updatedAt ?? '-'}
                                            </div>
                                        </section>

                                        {/* 中间区域固定展示交期与团队，列表纵向扫描时位置保持一致。 */}
                                        <section className="min-w-0 border-t border-[#eef0f3] pt-4 lg:border-l lg:border-t-0 lg:px-5 lg:pt-0">
                                            <div className="flex items-center gap-2 text-xs text-[#9ca3af]">
                                                <CalendarCheck className="size-4 text-[#5e6ad2]" />
                                                最终交期
                                            </div>
                                            <div className="mt-1 font-medium text-[#374151]">
                                                {assignment.finalDelivery ?? '待确认'}
                                            </div>

                                            <div className="mt-3 flex items-center gap-2 text-xs text-[#9ca3af]">
                                                <Users className="size-4 text-[#6e6e80]" />
                                                执行团队
                                            </div>
                                            <div
                                                className="mt-1 truncate text-sm text-[#4b5563]"
                                                title={assignment.assignees.map((member) => member.displayName).join('、')}
                                            >
                                                {assignment.assignees.map((member) => member.displayName).join('、') || '成员待同步'}
                                            </div>
                                        </section>

                                        {/* 右侧统一放金额和主操作，避免按钮在不同卡片中来回跳动。 */}
                                        <section className="flex items-center justify-between gap-4 border-t border-[#eef0f3] pt-4 lg:block lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0 lg:text-right">
                                            <div>
                                                <div className="text-xs text-[#9ca3af]">成交金额</div>
                                                <div className="mt-1 text-xl font-semibold text-[#4f46a5]">
                                                    {assignment.amountLabel}
                                                </div>
                                            </div>
                                            <Button asChild className="lg:mt-3" size="sm" variant="outline">
                                                <Link href={taskDetailUrl(assignment.taskId)}>进入任务</Link>
                                            </Button>
                                        </section>
                                    </CardContent>
                                </Card>
                            </article>
                        ))}
                    </div>
                )}

                <PaginationNav links={assignments.links} meta={assignments.meta} />
            </div>
        </AppLayout>
    );
}
