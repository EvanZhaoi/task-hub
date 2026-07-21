import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

export function NativeSelect({ className, ...props }: ComponentProps<'select'>) {
    // 这里先封装浏览器原生 select，保持代码少、表单语义清晰。
    // 后续如果需要搜索、多选或复杂交互，再替换为 Radix Select。
    return (
        <select
            className={cn(
                'h-10 rounded-md border border-[#d1d5db] bg-white px-3 text-sm text-[#374151] outline-none transition-colors',
                'focus:border-[#5e6ad2] focus:ring-2 focus:ring-[#5e6ad2]/15',
                className,
            )}
            {...props}
        />
    );
}
