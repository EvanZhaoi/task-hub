import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

type CardProps = ComponentProps<'div'> & {
    as?: 'article' | 'div' | 'section';
};

export function Card({ as: Comp = 'div', className, ...props }: CardProps) {
    return <Comp className={cn('rounded-lg border border-[#e5e7eb] bg-white', className)} {...props} />;
}

export function CardContent({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('p-4', className)} {...props} />;
}
