import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

export function Input({ className, ...props }: ComponentProps<'input'>) {
    return (
        <input
            className={cn(
                'h-10 w-full rounded-md border border-[#d1d5db] bg-white px-3 text-sm outline-none transition-colors',
                'focus:border-[#5e6ad2] focus:ring-2 focus:ring-[#5e6ad2]/15',
                className,
            )}
            {...props}
        />
    );
}
