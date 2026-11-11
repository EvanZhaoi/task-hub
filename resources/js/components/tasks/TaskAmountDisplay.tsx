import { cn } from '@/lib/utils';

type TaskAmountDisplayProps = {
    amountLabel: string;
    amountType: 'BUDGET' | 'FINAL';
    className?: string;
    size?: 'md' | 'lg';
};

/**
 * 统一展示任务预算或成交金额。
 *
 * 预算使用中性色，表达“尚未确定”；成交金额使用低饱和绿色，表达“已经确认”。
 * 除颜色外同时通过标签和背景区分，避免用户只依赖颜色判断金额含义。
 */
export function TaskAmountDisplay({
    amountLabel,
    amountType,
    className,
    size = 'md',
}: TaskAmountDisplayProps) {
    const isFinalAmount = amountType === 'FINAL';

    return (
        <div
            className={cn(
                'inline-flex min-w-36 flex-col rounded-lg border px-3.5 py-2.5',
                isFinalAmount
                    ? 'border-[#cfe8d8] bg-[#f1f8f4]'
                    : 'border-[#e5e7eb] bg-[#f8fafc]',
                className,
            )}
        >
            <span className={cn('text-xs', isFinalAmount ? 'text-[#397059]' : 'text-[#6b7280]')}>
                {isFinalAmount ? '成交金额' : '任务预算'}
            </span>
            <strong
                className={cn(
                    'mt-0.5 font-semibold tracking-normal',
                    size === 'lg' ? 'text-xl' : 'text-lg',
                    isFinalAmount ? 'text-[#166534]' : 'text-[#374151]',
                )}
            >
                {amountLabel}
            </strong>
        </div>
    );
}
