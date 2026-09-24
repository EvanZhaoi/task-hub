import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, FileText, Paperclip } from 'lucide-react';
import { useState, type ComponentProps, type FormEvent, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { DatePicker } from '@/components/common/DatePicker';
import { RichTextViewer } from '@/components/common/RichTextViewer';
import { TaskAttachmentUploader } from '@/components/tasks/TaskAttachmentUploader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { SharedPageProps } from '@/types/page';
import type {
    BidItem,
    BidStatus,
    TaskComplexity,
    TaskEventItem,
    TaskShowProps,
    TaskStatus,
    UploadedTaskAttachment,
} from '@/types/task';

// 状态文案和任务大厅保持一致；后续如果多个页面继续使用，可以再抽成 task display 配置文件。
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

const bidStatusLabels: Record<BidStatus, string> = {
    ACTIVE: '有效投标',
    WITHDRAWN: '已撤回',
    ACCEPTED: '已中标',
    LOST: '未中标',
};

const eventLabels: Record<string, string> = {
    TASK_CREATED: '创建任务',
    TASK_PUBLISHED: '发布任务',
    BID_SUBMITTED: '提交投标',
    BID_WITHDRAWN: '撤回投标',
    BID_ACCEPTED: '选定投标',
    TASK_ASSIGNED: '任务指派',
    CHANGE_REQUESTED: '发起变更',
    CHANGE_APPROVED: '变更通过',
    CHANGE_REJECTED: '变更拒绝',
    DELIVERY_SUBMITTED: '提交交付',
    DELIVERY_ACCEPTED: '验收通过',
    REVISION_REQUIRED: '要求修改',
    TASK_COMPLETED: '任务完成',
    TASK_CANCELLED: '任务取消',
    TASK_FAILED: '任务流标',
    BIDDING_DEADLINE_EXTENDED: '延长招标截止',
};

const statusBadgeVariants: Record<TaskStatus, ComponentProps<typeof Badge>['variant']> = {
    DRAFT: 'default',
    OPEN: 'open',
    PENDING_SELECTION: 'pending',
    ASSIGNED: 'assigned',
    COMPLETED: 'completed',
    FAILED: 'failed',
    CANCELLED: 'cancelled',
};

const complexityBadgeVariants: Record<TaskComplexity, ComponentProps<typeof Badge>['variant']> = {
    LOW: 'completed',
    MEDIUM: 'default',
    HIGH: 'danger',
};

const bidStatusBadgeVariants: Record<BidStatus, ComponentProps<typeof Badge>['variant']> = {
    ACTIVE: 'open',
    WITHDRAWN: 'failed',
    ACCEPTED: 'completed',
    LOST: 'cancelled',
};

type BidForm = {
    amount: string;
    deliveryDate: string;
    proposal: string;
    attachments: UploadedTaskAttachment[];
};

export default function TaskShow({ canPlaceBid, events, task, visibleBids }: TaskShowProps) {
    const { flash } = usePage<SharedPageProps>().props;

    return (
        <AppLayout
            actions={<PlaceBidDialog canPlaceBid={canPlaceBid} taskId={task.id} />}
            activeNav="tasks"
            subtitle="查看任务完整信息、投标记录和业务时间线"
            title="任务详情"
        >
            <div className="mb-4">
                <Button asChild size="sm" variant="outline">
                    <Link href="/tasks">
                        <ArrowLeft className="mr-2 size-4" />
                        返回任务大厅
                    </Link>
                </Button>
            </div>

            {flash?.success ? (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {flash.success}
                </div>
            ) : null}

            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="space-y-5">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="min-w-0">
                                    <div className="mb-3 flex flex-wrap items-center gap-2">
                                        <Badge variant={statusBadgeVariants[task.displayStatus]}>
                                            {statusLabels[task.displayStatus]}
                                        </Badge>
                                        <Badge variant={complexityBadgeVariants[task.complexity]}>
                                            {complexityLabels[task.complexity]}
                                        </Badge>
                                        <Badge variant="default">
                                            {task.assignmentType === 'BIDDING' ? '招标选标' : '直接指派'}
                                        </Badge>
                                    </div>
                                    <h2 className="m-0 text-2xl font-bold tracking-normal">{task.title}</h2>
                                    <p className="mt-2 text-sm text-[#6e6e80]">
                                        发布者：{task.createdByName}
                                        {task.departmentName ? ` · ${task.departmentName}` : ''}
                                    </p>
                                </div>
                                <div className="rounded-md bg-[#f7f7f8] px-4 py-3 text-right">
                                    <div className="text-xs text-[#6e6e80]">预算 / 最终金额</div>
                                    <div className="text-xl font-semibold text-[#1a1a1a]">{task.amountLabel}</div>
                                </div>
                            </div>

                            <div className="mt-6 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                                <InfoItem label="期望交付" value={task.expectedDelivery ?? '-'} />
                                <InfoItem label="最终交付" value={task.finalDelivery ?? '-'} />
                                <InfoItem label="招标截止" value={task.biddingDeadline ?? '-'} />
                                <InfoItem label="有效投标" value={`${task.activeBidCount} 个`} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <SectionTitle icon={<FileText className="size-4" />} title="任务描述" />
                            <RichTextViewer className="mt-4" html={task.description} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <SectionTitle icon={<Paperclip className="size-4" />} title="任务附件" />
                            {task.attachments.length > 0 ? (
                                <div className="mt-4 space-y-2">
                                    {task.attachments.map((file) => (
                                        <AttachmentRow id={file.id} key={file.id} name={file.name} />
                                    ))}
                                </div>
                            ) : (
                                <p className="mt-4 text-sm text-[#6e6e80]">暂无附件。</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <SectionTitle title="投标记录" />
                            <BidList bids={visibleBids} />
                        </CardContent>
                    </Card>
                </div>

                <aside className="space-y-5">
                    <Card>
                        <CardContent className="space-y-3 p-5">
                            <SectionTitle title="任务信息" />
                            <InfoItem label="付款账号" value={task.paymentAccountName ?? task.paymentAccountId} />
                            <InfoItem label="付款部门" value={task.paymentDepartmentName ?? '-'} />
                            <InfoItem label="发布时间" value={task.createdAt ?? '-'} />
                            <InfoItem label="更新时间" value={task.updatedAt ?? '-'} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5">
                            <SectionTitle title="业务时间线" />
                            <EventTimeline events={events} />
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </AppLayout>
    );
}

function PlaceBidDialog({ canPlaceBid, taskId }: { canPlaceBid: boolean; taskId: string }) {
    const [open, setOpen] = useState(false);
    const [uploadedAttachments, setUploadedAttachments] = useState<UploadedTaskAttachment[]>([]);
    const form = useForm<BidForm>({
        amount: '',
        deliveryDate: '',
        proposal: '',
        attachments: [],
    });

    function changeOpen(nextOpen: boolean): void {
        setOpen(nextOpen);

        if (!nextOpen && !form.processing) {
            // 关闭弹窗时清空本次投标表单；已经上传的总部文件不做远程删除。
            form.reset();
            setUploadedAttachments([]);
        }
    }

    function changeUploadedAttachments(files: UploadedTaskAttachment[]): void {
        setUploadedAttachments(files);
        // 发布投标时只提交总部文件 id/name，后端写入 attachment_ref。
        form.setData('attachments', files);
    }

    function submitBid(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.post(`/tasks/${taskId}/bids`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setUploadedAttachments([]);
                setOpen(false);
            },
        });
    }

    if (!canPlaceBid) {
        return (
            <Button disabled variant="muted">
                暂不可投标
            </Button>
        );
    }

    return (
        <Dialog modal={false} onOpenChange={changeOpen} open={open}>
            <DialogTrigger asChild>
                <Button type="button">我要投标</Button>
            </DialogTrigger>
            <DialogContent className="flex max-h-[90vh] max-w-2xl flex-col overflow-hidden p-0">
                <DialogHeader className="mb-0 rounded-t-lg border-b border-[#e5e7eb] bg-[#fbfbfc] px-6 py-4">
                    <DialogTitle>提交投标</DialogTitle>
                    <DialogDescription>
                        第一版先支持单人投标。提交后会生成一条 OWNER 投标成员记录，并进入发布者选标范围。
                    </DialogDescription>
                </DialogHeader>

                <form className="flex min-h-0 flex-1 flex-col" method="POST" onSubmit={submitBid}>
                    <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="投标金额" message={form.errors.amount} required>
                                <Input
                                    min="0"
                                    name="amount"
                                    onChange={(event) => form.setData('amount', event.target.value)}
                                    placeholder="2800.00"
                                    step="0.01"
                                    type="number"
                                    value={form.data.amount}
                                />
                            </Field>

                            <Field label="承诺交付日期" message={form.errors.deliveryDate} required>
                                <DatePicker
                                    name="deliveryDate"
                                    onChange={(value) => form.setData('deliveryDate', value)}
                                    value={form.data.deliveryDate}
                                />
                            </Field>
                        </div>

                        <Field label="投标说明" message={form.errors.proposal}>
                            <Textarea
                                disabled={form.processing}
                                name="proposal"
                                onChange={(event) => form.setData('proposal', event.target.value)}
                                placeholder="说明你的实现方案、交付范围和风险点"
                                value={form.data.proposal}
                            />
                        </Field>

                        <Field label="附件" message={form.errors.attachments}>
                            <TaskAttachmentUploader
                                disabled={form.processing}
                                onChange={changeUploadedAttachments}
                                value={uploadedAttachments}
                            />
                        </Field>
                    </div>

                    <DialogFooter className="mt-0 rounded-b-lg border-t border-[#e5e7eb] bg-[#fbfbfc] px-6 py-4">
                        <DialogClose asChild>
                            <Button disabled={form.processing} type="button" variant="outline">
                                取消
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing} type="submit">
                            {form.processing ? '提交中...' : '提交投标'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function BidList({ bids }: { bids: BidItem[] }) {
    if (bids.length === 0) {
        return <p className="mt-4 text-sm text-[#6e6e80]">暂无可查看的投标记录。</p>;
    }

    return (
        <div className="mt-4 space-y-3">
            {bids.map((bid) => {
                const owner = bid.members.find((member) => member.role === 'OWNER');

                return (
                    <div className="rounded-lg border border-[#e5e7eb] bg-[#fbfbfc] p-4" key={bid.id}>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <strong className="text-base">{bid.amountLabel}</strong>
                                    <Badge variant={bidStatusBadgeVariants[bid.status]}>
                                        {bidStatusLabels[bid.status]}
                                    </Badge>
                                </div>
                                <p className="mt-1 text-sm text-[#6e6e80]">
                                    主投标人：{owner?.userId ?? '-'} · 承诺交付：{bid.deliveryDate ?? '-'}
                                </p>
                            </div>
                            <span className="text-xs text-[#9ca3af]">第 {bid.revisionNo} 版 · {bid.createdAt}</span>
                        </div>

                        {bid.proposal ? <p className="mt-3 whitespace-pre-wrap text-sm leading-6">{bid.proposal}</p> : null}

                        {bid.attachments.length > 0 ? (
                            <div className="mt-3 space-y-2">
                                {bid.attachments.map((file) => (
                                    <AttachmentRow id={file.id} key={file.id} name={file.name} />
                                ))}
                            </div>
                        ) : null}
                    </div>
                );
            })}
        </div>
    );
}

function EventTimeline({ events }: { events: TaskEventItem[] }) {
    if (events.length === 0) {
        return <p className="mt-4 text-sm text-[#6e6e80]">暂无事件记录。</p>;
    }

    return (
        <ol className="mt-4 space-y-4">
            {events.map((event) => (
                <li className="relative border-l border-[#e5e7eb] pl-4" key={event.id}>
                    <span className="absolute -left-1.5 top-1 size-3 rounded-full bg-[#5e6ad2]" />
                    <div className="text-sm font-medium">{eventLabels[event.eventType] ?? event.eventType}</div>
                    <div className="mt-1 text-xs text-[#6e6e80]">
                        {event.createdAt ?? '-'}
                        {event.operatorId ? ` · ${event.operatorId}` : ''}
                    </div>
                    {event.remark ? <div className="mt-1 text-sm text-[#374151]">{event.remark}</div> : null}
                </li>
            ))}
        </ol>
    );
}

function AttachmentRow({ id, name }: { id: string; name: string }) {
    return (
        <div className="flex min-w-0 items-center gap-2 rounded-md border border-[#e5e7eb] bg-white px-3 py-2 text-sm">
            <Paperclip className="size-4 shrink-0 text-[#6e6e80]" />
            <span className="min-w-0 truncate text-[#374151]">{name}</span>
            <span className="shrink-0 text-xs text-[#9ca3af]">{id}</span>
        </div>
    );
}

function SectionTitle({ icon, title }: { icon?: ReactNode; title: string }) {
    return (
        <div className="flex items-center gap-2 text-sm font-semibold text-[#374151]">
            {icon}
            <span>{title}</span>
        </div>
    );
}

function InfoItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <div className="text-xs text-[#9ca3af]">{label}</div>
            <div className="mt-1 min-w-0 truncate text-sm font-medium text-[#1a1a1a]">{value}</div>
        </div>
    );
}

type FieldProps = {
    children: ReactNode;
    label: string;
    message?: string;
    required?: boolean;
};

function Field({ children, label, message, required = false }: FieldProps) {
    return (
        <div className="block min-w-0 space-y-1.5">
            <div className="text-sm font-medium text-[#374151]">
                {label}
                {required ? <span className="ml-1 text-red-500">*</span> : null}
            </div>
            {children}
            {message ? <span className="block text-xs leading-5 text-red-600">{message}</span> : null}
        </div>
    );
}
