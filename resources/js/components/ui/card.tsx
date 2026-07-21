import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

type CardProps = ComponentProps<'div'> & {
    // 根据语义选择外层标签：列表项可用 article，普通容器用 div，独立区域用 section。
    as?: 'article' | 'div' | 'section';
};

export function Card({ as: Comp = 'div', className, ...props }: CardProps) {
    // Card 只负责基础边框、圆角、背景；内边距交给 CardContent 或页面控制。
    return <Comp className={cn('rounded-lg border border-[#e5e7eb] bg-white', className)} {...props} />;
}

export function CardContent({ className, ...props }: ComponentProps<'div'>) {
    // 统一默认内边距，页面可通过 className 覆盖，例如空状态使用 p-8。
    return <div className={cn('p-4', className)} {...props} />;
}
