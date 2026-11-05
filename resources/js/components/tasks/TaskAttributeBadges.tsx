import type { ComponentProps } from 'react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { TaskComplexity, TaskStatus } from '@/types/task';

// 状态文案和视觉类型集中维护，保证任务大厅与详情页不会出现两套显示规则。
const statusDisplay: Record<
    TaskStatus,
    { label: string; variant: ComponentProps<typeof Badge>['variant'] }
> = {
    DRAFT: { label: '草稿', variant: 'default' },
    OPEN: { label: '招标中', variant: 'open' },
    PENDING_SELECTION: { label: '待选标', variant: 'pending' },
    ASSIGNED: { label: '进行中', variant: 'assigned' },
    COMPLETED: { label: '已完成', variant: 'completed' },
    FAILED: { label: '已流标', variant: 'failed' },
    CANCELLED: { label: '已取消', variant: 'cancelled' },
};

// 复杂度使用低饱和描边标签，与实心状态标签区分，避免用户把两者混为同一类状态。
const complexityDisplay: Record<TaskComplexity, { className: string; label: string }> = {
    LOW: {
        className: 'border border-emerald-200 bg-emerald-50 text-emerald-700',
        label: '简单',
    },
    MEDIUM: {
        className: 'border border-amber-200 bg-amber-50 text-amber-700',
        label: '中等',
    },
    HIGH: {
        className: 'border border-violet-200 bg-violet-50 text-violet-700',
        label: '复杂',
    },
};

type TaskAttributeBadgesProps = {
    className?: string;
    complexity: TaskComplexity;
    displayStatus: TaskStatus;
    isUrgent: boolean;
};

/**
 * 统一展示任务状态、复杂度和紧急情况。
 *
 * 两个页面共同使用该业务组件，后续调整标签文案或颜色时只需要修改一个文件。
 */
export function TaskAttributeBadges({
    className,
    complexity,
    displayStatus,
    isUrgent,
}: TaskAttributeBadgesProps) {
    const status = statusDisplay[displayStatus];
    const complexityInfo = complexityDisplay[complexity];

    return (
        <div className={cn('flex flex-wrap items-center gap-2', className)}>
            <Badge variant={status.variant}>{status.label}</Badge>
            <Badge className={cn('whitespace-nowrap', complexityInfo.className)} variant="default">
                复杂度：{complexityInfo.label}
            </Badge>
            <Badge
                className={cn('whitespace-nowrap', !isUrgent && 'bg-slate-50 text-slate-600')}
                variant={isUrgent ? 'danger' : 'default'}
            >
                {isUrgent ? '紧急' : '非紧急'}
            </Badge>
        </div>
    );
}
