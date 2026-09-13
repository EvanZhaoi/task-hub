import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

export function Card({ className, ...props }: ComponentProps<'div'>) {
    // Card 只负责基础边框、圆角、背景；内边距交给 CardContent 或页面控制。
    // 保持标准 shadcn 写法：Card 自身是 div；需要 article/section 语义时在外层包语义标签。
    return <div className={cn('rounded-lg border border-[#e5e7eb] bg-white', className)} {...props} />;
}

export function CardContent({ className, ...props }: ComponentProps<'div'>) {
    // 统一默认内边距，页面可通过 className 覆盖，例如空状态使用 p-8。
    return <div className={cn('p-4', className)} {...props} />;
}
