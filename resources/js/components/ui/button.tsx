import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// Button 是 TaskHub 后续所有按钮的基础组件。
// 业务页面应该优先表达语义：primary、outline、ghost，而不是重复写完整 Tailwind。
const buttonVariants = cva(
    'inline-flex h-10 items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors outline-none disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                // primary 用于页面主动作，例如查询、提交、确认。
                primary: 'bg-[#5e6ad2] text-white hover:bg-[#4f5bd5]',
                // secondary 用于当前选中或较弱强调。
                secondary: 'bg-[#f5f3ff] text-[#5e6ad2] hover:bg-[#ede9fe]',
                // outline 用于重置、翻页、退出等非主动作。
                outline: 'border border-[#d1d5db] bg-white text-[#4b5563] hover:border-[#9ca3af] hover:bg-[#f9fafb]',
                // ghost 用于导航或工具栏里的轻量按钮。
                ghost: 'text-[#6e6e80] hover:bg-[#f3f4f6] hover:text-[#1a1a1a]',
                // muted 用于不可用但需要占位的按钮，例如没有上一页。
                muted: 'border border-[#e5e7eb] bg-[#f9fafb] text-[#9ca3af]',
            },
            size: {
                // default 对齐常规表单控件高度。
                default: 'px-4',
                // sm 适合顶部导航和紧凑操作区。
                sm: 'h-8 px-3',
                // icon 预留给后续 lucide 图标按钮。
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
        // asChild 来自 shadcn/ui 常用模式，可以让 a 标签获得按钮样式，同时保留链接语义。
        asChild?: boolean;
    };

export function Button({ asChild = false, className, size, variant, ...props }: ButtonProps) {
    // asChild 用来把按钮样式套到 a/form submit 等元素上，避免为链接按钮重复写 className。
    const Comp = asChild ? Slot : 'button';

    return <Comp className={cn(buttonVariants({ variant, size }), className)} {...props} />;
}

export { buttonVariants };
