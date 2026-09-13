import * as PopoverPrimitive from '@radix-ui/react-popover';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// Popover 是 shadcn/ui 常用基础组件。
// 它只负责“点击触发器后展示浮层”，不包含任何 TaskHub 业务规则。
export const Popover = PopoverPrimitive.Root;

// Trigger 保留 Radix 原始能力，业务组件通常配合 asChild 使用 Button 作为触发器。
export const PopoverTrigger = PopoverPrimitive.Trigger;

export function PopoverContent({ align = 'start', className, sideOffset = 8, ...props }: ComponentProps<typeof PopoverPrimitive.Content>) {
    return (
        <PopoverPrimitive.Content
            align={align}
            className={cn(
                // Dialog 是 modal 时会用 DismissableLayer 管理弹窗外部点击。
                // Popover 放在 Dialog 内使用时，如果再 Portal 到 body，可能被 Dialog 当成外部内容拦截 pointer event。
                // 因此这里先让 PopoverContent 留在当前 DOM 层级，保证日期选择器和搜索选择器在 Dialog 内可交互。
                'z-50 rounded-md border border-[#e5e7eb] bg-white p-3 text-[#1a1a1a] shadow-lg outline-none',
                className,
            )}
            sideOffset={sideOffset}
            {...props}
        />
    );
}
