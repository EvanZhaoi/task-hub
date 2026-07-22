import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

export const Dialog = DialogPrimitive.Root;

export const DialogTrigger = DialogPrimitive.Trigger;

export const DialogClose = DialogPrimitive.Close;

export function DialogContent({ className, ...props }: ComponentProps<typeof DialogPrimitive.Content>) {
    return (
        <DialogPrimitive.Portal>
            {/* Overlay 使用固定定位盖住整页，让用户明确当前正在处理弹窗内表单。 */}
            <DialogPrimitive.Overlay className="fixed inset-0 z-40 bg-black/25" />
            <DialogPrimitive.Content
                className={cn(
                    'fixed left-1/2 top-1/2 z-50 max-h-[90vh] w-[calc(100vw-2rem)] max-w-2xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg border border-[#e5e7eb] bg-white p-6 shadow-lg outline-none',
                    className,
                )}
                {...props}
            />
        </DialogPrimitive.Portal>
    );
}

export function DialogHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('mb-5 space-y-1', className)} {...props} />;
}

export function DialogTitle({ className, ...props }: ComponentProps<typeof DialogPrimitive.Title>) {
    return <DialogPrimitive.Title className={cn('text-lg font-semibold text-[#1a1a1a]', className)} {...props} />;
}

export function DialogDescription({ className, ...props }: ComponentProps<typeof DialogPrimitive.Description>) {
    return (
        <DialogPrimitive.Description className={cn('text-sm leading-6 text-[#6e6e80]', className)} {...props} />
    );
}

export function DialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('mt-6 flex justify-end gap-3', className)} {...props} />;
}
