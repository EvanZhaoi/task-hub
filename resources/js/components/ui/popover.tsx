import * as PopoverPrimitive from '@radix-ui/react-popover';
import { Branch as DismissableLayerBranch } from '@radix-ui/react-dismissable-layer';
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
            <DismissableLayerBranch>
                <PopoverPrimitive.Content
                    align={align}
                    className={cn(
                        // Popover 放在 Dialog 的滚动区域里时，如果留在原 DOM 层级，会被 Dialog 的
                        // overflow-hidden / overflow-y-auto 裁剪；因此需要 Portal 到 body。
                        // 但 Dialog 是 modal，会通过 DismissableLayer 拦截“弹窗外”的点击和聚焦。
                        // DismissableLayerBranch 用来告诉 Radix：这个 Portal 出去的浮层仍属于当前弹窗的可交互区域。
                        'z-[80] rounded-md border border-[#e5e7eb] bg-white p-3 text-[#1a1a1a] shadow-lg outline-none',
                        className,
                    )}
                    sideOffset={sideOffset}
                    {...props}
                />
            </DismissableLayerBranch>
        </PopoverPrimitive.Portal>
    );
}
