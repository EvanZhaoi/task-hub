import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

const badgeVariants = cva('inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', {
    variants: {
        variant: {
            default: 'bg-gray-100 text-gray-700',
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
    return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}
