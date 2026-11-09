import { Link } from '@inertiajs/react';
import { CalendarDays, Users } from 'lucide-react';
import type { ComponentProps } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { MyTaskFilters } from '@/components/my-tasks/MyTaskFilters';
import { PaginationNav } from '@/components/my-tasks/PaginationNav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { BidStatus, MyBidsProps } from '@/types/task';
import { taskDetailUrl } from '@/utils/task-navigation';

const statusOptions = [
    { label: '全部状态', value: 'ALL' },
    { label: '有效投标', value: 'ACTIVE' },
    { label: '已中标', value: 'ACCEPTED' },
    { label: '未中标', value: 'LOST' },
    { label: '已撤回', value: 'WITHDRAWN' },
];

const statusDisplay: Record<BidStatus, { label: string; variant: ComponentProps<typeof Badge>['variant'] }> = {
    ACTIVE: { label: '有效投标', variant: 'open' },
    ACCEPTED: { label: '已中标', variant: 'completed' },
    LOST: { label: '未中标', variant: 'cancelled' },
    WITHDRAWN: { label: '已撤回', variant: 'failed' },
};

/**
 * 当前用户投标记录工作台，OWNER 和 COLLABORATOR 都能在这里找到相关方案。
 */
export default function MyBids({ bids, filters }: MyBidsProps) {
    return (
        <AppLayout activeNav="my-tasks" subtitle="查看你作为主投标人或协作成员参与的全部投标历史。" title="我的投标">
            <div className="space-y-5">
                <MyTaskFilters action="/my-tasks/bids" filters={filters} placeholder="搜索任务标题或投标说明" statusOptions={statusOptions} />

                {bids.data.length === 0 ? (
                    <Card><CardContent className="p-10 text-center text-sm text-[#6e6e80]">没有找到符合条件的投标记录。</CardContent></Card>
                ) : (
                    <div className="space-y-3">
                        {bids.data.map((bid) => {
                            const state = statusDisplay[bid.status];

                            return (
                                <article key={bid.id}>
                                    <Card className="transition-colors hover:border-[#c7c9e8]">
                                        <CardContent className="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_190px_150px] lg:items-center">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Link className="truncate text-base font-semibold hover:text-[#5e6ad2]" href={taskDetailUrl(bid.taskId)}>{bid.taskTitle}</Link>
                                                    <Badge variant={state.variant}>{state.label}</Badge>
                                                    <Badge variant="default">{bid.role === 'OWNER' ? '主投标人' : '协作成员'}</Badge>
                                                </div>
                                                <p className="mt-2 line-clamp-2 text-sm leading-6 text-[#6e6e80]">{bid.proposal || '未填写投标说明。'}</p>
                                                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#6e6e80]">
                                                    <span>第 {bid.revisionNo} 版</span>
                                                    <span>提交于 {bid.createdAt ?? '-'}</span>
                                                    <span className="inline-flex items-center gap-1"><Users className="size-3.5" />{bid.members.map((member) => member.displayName).join('、')}</span>
                                                </div>
                                            </div>

                                            <div>
                                                <div className="text-xs text-[#6e6e80]">我的报价</div>
                                                <div className="mt-1 text-xl font-semibold text-[#4f46a5]">{bid.amountLabel}</div>
                                                <div className="mt-2 flex items-center gap-1.5 text-sm text-[#6e6e80]"><CalendarDays className="size-4" />{bid.deliveryDate ?? '-'}</div>
                                            </div>

                                            <div className="lg:text-right">
                                                <Button asChild size="sm" variant="outline"><Link href={taskDetailUrl(bid.taskId)}>查看任务详情</Link></Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </article>
                            );
                        })}
                    </div>
                )}

                <PaginationNav links={bids.links} meta={bids.meta} />
            </div>
        </AppLayout>
    );
}
