import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// Badge 用于状态、复杂度等短标签。
// 使用 cva 统一维护颜色方案，业务页面只传 variant，不再重复写背景色和文字色。
const badgeVariants = cva('inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', {
    variants: {
        variant: {
            // default 作为中性标签，适合草稿、中等复杂度等不强调状态。
            default: 'bg-gray-100 text-gray-700',
            // 以下颜色和原型图保持克制、清晰，避免整页变成单一高饱和色。
            open: 'bg-blue-100 text-blue-800',
            pending: 'bg-amber-100 text-amber-800',
            assigned: 'bg-orange-100 text-orange-800',
            completed: 'bg-emerald-100 text-emerald-800',
            failed: 'bg-gray-200 text-gray-700',
            cancelled: 'bg-gray-50 text-gray-400 line-through',
            danger: 'bg-red-100 text-red-800',
        },
    },
    defaultVariants: {
        variant: 'default',
    },
});

type BadgeProps = ComponentProps<'span'> & VariantProps<typeof badgeVariants>;

export function Badge({ className, variant, ...props }: BadgeProps) {
    // 外部 className 放在最后合并，允许页面在必要时做局部微调。
    return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}
