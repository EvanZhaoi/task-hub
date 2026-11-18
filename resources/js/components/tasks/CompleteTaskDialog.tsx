import { useForm } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type CompleteTaskDialogProps = {
    returnTo: string;
    taskId: string;
    version: number;
};

/**
 * 发布者确认任务完成的 Phase 1 弹窗。
 *
 * 页面只提交当前任务版本和安全返回地址。后端会锁定 Task 并重新校验发布者、状态、
 * 执行成员和 version，不能依赖前端按钮控制保证业务合法性。
 */
export function CompleteTaskDialog({ returnTo, taskId, version }: CompleteTaskDialogProps) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        // 乐观锁版本用于识别用户打开详情后发生的其它任务更新。
        version,
        // 完成后仍然保留用户进入详情页之前的列表筛选条件。
        returnTo,
    });

    /**
     * 提交确认完成请求；成功后关闭弹窗并使用后端返回的新 Props 刷新详情。
     */
    function completeTask(): void {
        form.post(`/tasks/${taskId}/complete`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog onOpenChange={setOpen} open={open}>
            <DialogTrigger asChild>
                <Button type="button">
                    <CheckCircle2 className="mr-1.5 size-4" />
                    完成任务
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>确认完成任务？</DialogTitle>
                    <DialogDescription>
                        第一期暂未接入正式交付验收流程。确认后任务将直接标记为已完成，并且不能自行撤销。
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-md border border-[#dce9e1] bg-[#f4faf6] px-4 py-3 text-sm leading-6 text-[#356149]">
                    完成记录会写入业务时间线，并通知任务负责人和协作成员。
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button disabled={form.processing} type="button" variant="outline">
                            取消
                        </Button>
                    </DialogClose>
                    <Button disabled={form.processing} onClick={completeTask} type="button">
                        {form.processing ? '处理中...' : '确认完成'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
