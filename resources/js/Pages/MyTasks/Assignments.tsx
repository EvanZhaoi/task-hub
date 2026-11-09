import { Link } from '@inertiajs/react';
import { CalendarCheck, Users } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { RichTextViewer } from '@/components/common/RichTextViewer';
import { MyTaskFilters } from '@/components/my-tasks/MyTaskFilters';
import { PaginationNav } from '@/components/my-tasks/PaginationNav';
import { TaskAttributeBadges } from '@/components/tasks/TaskAttributeBadges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { AssignedTasksProps } from '@/types/task';
import { taskDetailUrl } from '@/utils/task-navigation';

const statusOptions = [
    { label: '全部状态', value: 'ALL' },
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
                    <div className="grid gap-4 xl:grid-cols-2">
                        {assignments.data.map((assignment) => (
                            <article key={assignment.taskId}>
                                <Card className="h-full transition-colors hover:border-[#c7c9e8]">
                                    <CardContent className="flex h-full flex-col gap-4 p-5">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <Link className="text-base font-semibold hover:text-[#5e6ad2]" href={taskDetailUrl(assignment.taskId)}>{assignment.title}</Link>
                                                <TaskAttributeBadges className="mt-2" complexity={assignment.complexity} displayStatus={assignment.status} isUrgent={assignment.isUrgent} />
                                            </div>
                                            <Badge variant="default">{assignment.role === 'OWNER' ? '任务负责人' : '协作成员'}</Badge>
                                        </div>

                                        <RichTextViewer className="line-clamp-2" html={assignment.description} />

                                        <div className="grid grid-cols-2 gap-3 rounded-md bg-[#f8f9fb] p-3">
                                            <div>
                                                <div className="text-xs text-[#6e6e80]">成交金额</div>
                                                <div className="mt-1 font-semibold text-[#4f46a5]">{assignment.amountLabel}</div>
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-1 text-xs text-[#6e6e80]"><CalendarCheck className="size-3.5" />最终交期</div>
                                                <div className="mt-1 font-medium text-[#374151]">{assignment.finalDelivery ?? '待确认'}</div>
                                            </div>
                                        </div>

                                        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-[#eeeeef] pt-4">
                                            <div className="flex min-w-0 items-center gap-2 text-xs text-[#6e6e80]">
                                                <Users className="size-4 shrink-0" />
                                                <span className="truncate">{assignment.assignees.map((member) => member.displayName).join('、') || '成员待同步'}</span>
                                            </div>
                                            <Button asChild size="sm" variant="outline"><Link href={taskDetailUrl(assignment.taskId)}>进入任务</Link></Button>
                                        </div>
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
