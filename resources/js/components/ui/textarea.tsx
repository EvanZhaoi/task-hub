import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

export function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(
                'min-h-24 w-full rounded-md border border-[#d1d5db] bg-white px-3 py-2 text-sm leading-6 outline-none transition-colors',
                'focus:border-[#5e6ad2] focus:ring-2 focus:ring-[#5e6ad2]/15',
                className,
            )}
            {...props}
        />
    );
}
