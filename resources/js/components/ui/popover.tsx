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
        <PopoverPrimitive.Portal>
            <PopoverPrimitive.Content
                align={align}
                className={cn(
                    // z-50 保证日期浮层显示在 Dialog 上方；宽度由调用方按业务控件决定。
                    'z-50 rounded-md border border-[#e5e7eb] bg-white p-3 text-[#1a1a1a] shadow-lg outline-none',
                    className,
                )}
                sideOffset={sideOffset}
                {...props}
            />
        </PopoverPrimitive.Portal>
    );
}
