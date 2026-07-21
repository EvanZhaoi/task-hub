import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

export function Input({ className, ...props }: ComponentProps<'input'>) {
    return (
        <input
            className={cn(
                // 表单控件高度统一为 h-10，保证输入框、下拉框、按钮在同一行对齐。
                'h-10 w-full rounded-md border border-[#d1d5db] bg-white px-3 text-sm outline-none transition-colors',
                // focus 样式保持品牌色，但使用低透明 ring，避免视觉过重。
                'focus:border-[#5e6ad2] focus:ring-2 focus:ring-[#5e6ad2]/15',
                className,
            )}
            {...props}
        />
    );
}
