import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { DatePicker } from '@/components/common/DatePicker';
import { DateTimePicker } from '@/components/common/DateTimePicker';
import { PaymentAccountCombobox } from '@/components/common/PaymentAccountCombobox';
import { RichTextEditor } from '@/components/common/RichTextEditor';
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
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import type { SharedPageProps } from '@/types/page';
import type { TaskComplexity, TaskFilters, TaskIndexProps, TaskStatus, UploadedTaskAttachment } from '@/types/task';
import { urlWithQuery } from '@/utils/url';
import { useState, type ComponentProps, type ReactNode } from 'react';

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

// 当前数据库没有独立的紧急程度字段，列表沿用 complexity 的三级值做紧凑展示。
// 这里只改变列表文案，不改变筛选条件、提交值或后端业务含义。
const urgencyLabels: Record<TaskComplexity, string> = {
    LOW: '低',
    MEDIUM: '中',
    HIGH: '高',
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

type TaskCreateForm = {
    title: string;
    description: string;
    budget: string;
    expectedDelivery: string;
    biddingDeadline: string;
    complexity: TaskComplexity;
    paymentAccountId: string;
    attachments: UploadedTaskAttachment[];
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

/**
 * 根据后端返回的本地时间计算招标剩余天数提示。
 * 后端格式为 YYYY-MM-DD HH:mm，替换空格后按浏览器本地时区解析即可。
 */
function biddingRemainingLabel(deadline: string | null): string | null {
    if (!deadline) {
        return null;
    }

    const deadlineTime = new Date(deadline.replace(' ', 'T')).getTime();

    if (Number.isNaN(deadlineTime)) {
        return null;
    }

    const remainingMilliseconds = deadlineTime - Date.now();

    if (remainingMilliseconds <= 0) {
        return '已截止';
    }

    const remainingDays = Math.ceil(remainingMilliseconds / (24 * 60 * 60 * 1000));

    return remainingDays === 1 ? '1 天内截止' : `剩余 ${remainingDays} 天`;
}

export default function TaskIndex({
    complexityOptions,
    filters,
    paymentAccountOptions,
    statusOptions,
    tasks,
}: TaskIndexProps) {
    const { flash } = usePage<SharedPageProps>().props;
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [uploadedAttachments, setUploadedAttachments] = useState<UploadedTaskAttachment[]>([]);
    const form = useForm<TaskCreateForm>({
        title: '',
        description: '',
        budget: '',
        expectedDelivery: '',
        biddingDeadline: '',
        complexity: 'MEDIUM',
        paymentAccountId: '',
        attachments: [],
    });

    function changeCreateOpen(nextOpen: boolean): void {
        setIsCreateOpen(nextOpen);

        if (!nextOpen && !form.processing) {
            // 用户主动关闭弹窗时清空本次表单状态；已经上传到总部的文件不做远程删除。
            form.reset();
            setUploadedAttachments([]);
        }
    }

    function changeUploadedAttachments(files: UploadedTaskAttachment[]): void {
        setUploadedAttachments(files);
        // 上传接口已经返回总部文件 ID 和名称，发布任务时直接提交结构化数组。
        // 后端只保存 attachment_id / attachment_name，不保存总部完整响应。
        form.setData('attachments', files);
    }

    function submitCreateTask(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.post('/tasks', {
            preserveScroll: true,
            onSuccess: () => {
                // 发布成功后关闭弹窗并清空表单，任务列表由 Inertia 自动刷新。
                form.reset();
                setUploadedAttachments([]);
                setIsCreateOpen(false);
            },
        });
    }

    return (
        <AppLayout
            actions={
                <Dialog modal={false} onOpenChange={changeCreateOpen} open={isCreateOpen}>
                    <DialogTrigger asChild>
                        <Button type="button">发布任务</Button>
                    </DialogTrigger>
                    <DialogContent className="flex max-h-[90vh] max-w-3xl flex-col overflow-hidden p-0">
                        <DialogHeader className="mb-0 rounded-t-lg border-b border-[#e5e7eb] bg-[#fbfbfc] px-6 py-4">
                            <DialogTitle>发布任务</DialogTitle>
                            <DialogDescription>
                                当前阶段发布的是招标任务，发布后进入招标中。附件选择文件后会立即上传到总部文件服务。
                            </DialogDescription>
                        </DialogHeader>

                        <form className="flex min-h-0 flex-1 flex-col" method="POST" onSubmit={submitCreateTask}>
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                                <Field label="任务标题" message={form.errors.title} required>
                                    <Input
                                        autoFocus
                                        className="w-full"
                                        name="title"
                                        onChange={(event) => form.setData('title', event.target.value)}
                                        placeholder="例如：用户登录页 UI 重构"
                                        value={form.data.title}
                                    />
                                </Field>

                                <Field label="任务描述" message={form.errors.description}>
                                    <RichTextEditor
                                        disabled={form.processing}
                                        name="description"
                                        onChange={(value) => form.setData('description', value)}
                                        placeholder="说明背景、目标、验收标准和注意事项"
                                        value={form.data.description}
                                    />
                                </Field>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field label="预算金额" message={form.errors.budget} required>
                                        <Input
                                            className="w-full"
                                            min="0"
                                            name="budget"
                                            onChange={(event) => form.setData('budget', event.target.value)}
                                            placeholder="3000.00"
                                            step="0.01"
                                            type="number"
                                            value={form.data.budget}
                                        />
                                    </Field>

                                    <Field label="复杂度" message={form.errors.complexity} required>
                                        <NativeSelect
                                            className="w-full min-w-0"
                                            name="complexity"
                                            onChange={(event) =>
                                                form.setData('complexity', event.target.value as TaskComplexity)
                                            }
                                            value={form.data.complexity}
                                        >
                                            <option value="LOW">简单</option>
                                            <option value="MEDIUM">中等</option>
                                            <option value="HIGH">复杂</option>
                                        </NativeSelect>
                                    </Field>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field label="期望交付日期" message={form.errors.expectedDelivery} required>
                                        <DatePicker
                                            name="expectedDelivery"
                                            onChange={(value) => form.setData('expectedDelivery', value)}
                                            value={form.data.expectedDelivery}
                                        />
                                    </Field>

                                    <Field label="招标截止时间" message={form.errors.biddingDeadline} required>
                                        <DateTimePicker
                                            name="biddingDeadline"
                                            onChange={(value) => form.setData('biddingDeadline', value)}
                                            value={form.data.biddingDeadline}
                                        />
                                    </Field>
                                </div>

                                <Field label="付款账号" message={form.errors.paymentAccountId} required>
                                    <PaymentAccountCombobox
                                        disabled={paymentAccountOptions.length === 0}
                                        name="paymentAccountId"
                                        onChange={(value) => form.setData('paymentAccountId', value)}
                                        options={paymentAccountOptions}
                                        value={form.data.paymentAccountId}
                                    />
                                    {paymentAccountOptions.length === 0 ? (
                                        <span className="block text-xs leading-5 text-amber-700">
                                            付款账号接口未配置或暂时不可用，请确认外部接口后再发布任务。
                                        </span>
                                    ) : null}
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
                                <Button disabled={form.processing || paymentAccountOptions.length === 0} type="submit">
                                    {form.processing ? '发布中...' : '发布'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            }
            activeNav="tasks"
            subtitle="所有公开任务 · 可投标 · 支持状态、复杂度和关键词筛选"
            title="任务大厅"
        >
            {flash?.success ? (
                <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {flash.success}
                </div>
            ) : null}

            <Card className="mb-4">
                <CardContent>
                    {/* GET 表单适合列表筛选：URL 可复制、可刷新、可用于浏览器前进后退。 */}
                    <form action="/tasks" className="flex flex-col gap-3 lg:flex-row lg:items-center" method="GET">
                        {/* 状态由下方 Tab 控制；提交搜索或复杂度筛选时用 hidden 保留当前状态。 */}
                        {filters.status !== 'ALL' ? <input name="status" type="hidden" value={filters.status} /> : null}

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

                        <div className="relative min-w-0 lg:w-44">
                            <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#9ca3af]">
                                复杂度：
                            </span>
                            <NativeSelect
                                className="w-full pl-[4.5rem]"
                                defaultValue={filters.complexity}
                                name="complexity"
                            >
                                {complexityOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>

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
                    tasks.data.map((task) => {
                        const remainingLabel = biddingRemainingLabel(task.biddingDeadline);

                        return (
                            // 三块式卡片把任务、时间和预算信息固定在各自区域，便于纵向扫描比较。
                            <article key={task.id}>
                                <Card className="p-4 transition hover:border-[#c7d2fe] hover:shadow-sm sm:p-5">
                                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_13rem_10rem] lg:items-stretch lg:gap-0">
                                        {/* 左侧：标题、状态、紧急程度、描述摘要和交付日期。 */}
                                        <section className="min-w-0 lg:pr-6">
                                            <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                <h2 className="m-0 min-w-0 text-base font-semibold">
                                                    <Link
                                                        className="block truncate hover:text-[#5e6ad2] hover:underline"
                                                        href={`/tasks/${task.id}`}
                                                    >
                                                        {task.title}
                                                    </Link>
                                                </h2>
                                                <Badge variant={statusBadgeVariants[task.displayStatus]}>
                                                    {statusLabels[task.displayStatus]}
                                                </Badge>
                                                <Badge
                                                    title={`紧急程度：${urgencyLabels[task.complexity]}`}
                                                    variant={complexityBadgeVariants[task.complexity]}
                                                >
                                                    紧急程度：{urgencyLabels[task.complexity]}
                                                </Badge>
                                            </div>

                                            <RichTextViewer
                                                className="mt-2 line-clamp-2 max-w-none text-sm leading-5 [&_*]:m-0"
                                                html={task.description}
                                            />

                                            <div className="mt-3 flex items-center gap-2 text-xs text-[#6e6e80]">
                                                <span className="text-[#9ca3af]">预计交付</span>
                                                <span className="font-medium text-[#374151]">
                                                    {task.finalDelivery ?? task.expectedDelivery ?? '-'}
                                                </span>
                                            </div>
                                        </section>

                                        {/* 中间：只展示人员和招标时间，避免与左右两侧信息重复。 */}
                                        <section className="min-w-0 border-t border-[#eef0f3] pt-4 text-sm lg:border-l lg:border-t-0 lg:px-6 lg:pt-0">
                                            <div>
                                                <span className="block text-xs text-[#9ca3af]">发布者</span>
                                                <div className="mt-1 truncate font-medium text-[#1f2937]">
                                                    {task.createdByName}
                                                </div>
                                                {task.departmentName ? (
                                                    <div className="mt-0.5 truncate text-xs text-[#9ca3af]">
                                                        {task.departmentName}
                                                    </div>
                                                ) : null}
                                            </div>

                                            <div className="mt-3">
                                                <span className="block text-xs text-[#9ca3af]">招标截止</span>
                                                <div className="mt-1 font-medium text-[#1f2937]">
                                                    {task.biddingDeadline ?? '-'}
                                                </div>
                                                {remainingLabel ? (
                                                    <div className="mt-0.5 text-xs text-[#5e6ad2]">{remainingLabel}</div>
                                                ) : null}
                                            </div>
                                        </section>

                                        {/* 右侧：突出预算，并在同一区域展示当前有效投标人数。 */}
                                        <section className="flex min-w-0 flex-col border-t border-[#eef0f3] pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0 lg:text-right">
                                            <span className="text-xs text-[#9ca3af]">任务预算</span>
                                            <strong className="mt-1 text-xl font-semibold text-[#111827]">
                                                {task.amountLabel}
                                            </strong>
                                            <span className="mt-2 text-sm text-[#6e6e80]">
                                                {task.activeBidCount} 人投标
                                            </span>
                                            <Link
                                                className="mt-auto pt-3 text-sm font-medium text-[#5e6ad2] hover:underline"
                                                href={`/tasks/${task.id}`}
                                            >
                                                查看详情
                                            </Link>
                                        </section>
                                    </div>
                                </Card>
                            </article>
                        );
                    })
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
