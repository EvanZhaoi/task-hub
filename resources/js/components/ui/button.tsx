import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex h-10 items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors outline-none disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                primary: 'bg-[#5e6ad2] text-white hover:bg-[#4f5bd5]',
                secondary: 'bg-[#f5f3ff] text-[#5e6ad2] hover:bg-[#ede9fe]',
                outline: 'border border-[#d1d5db] bg-white text-[#4b5563] hover:border-[#9ca3af] hover:bg-[#f9fafb]',
                ghost: 'text-[#6e6e80] hover:bg-[#f3f4f6] hover:text-[#1a1a1a]',
                muted: 'border border-[#e5e7eb] bg-[#f9fafb] text-[#9ca3af]',
            },
            size: {
                default: 'px-4',
                sm: 'h-8 px-3',
                icon: 'size-8 p-0',
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'default',
        },
    },
);

type ButtonProps = ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
    };

export function Button({ asChild = false, className, size, variant, ...props }: ButtonProps) {
    // asChild 用来把按钮样式套到 a/form submit 等元素上，避免为链接按钮重复写 className。
    const Comp = asChild ? Slot : 'button';

    return <Comp className={cn(buttonVariants({ variant, size }), className)} {...props} />;
}

export { buttonVariants };
