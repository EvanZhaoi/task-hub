import { useForm } from '@inertiajs/react';

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

type WithdrawBidDialogProps = {
    bidId: string;
    returnTo: string;
    taskId: string;
};

/**
 * 撤回投标确认弹窗。
 *
 * 页面只提交投标 ID 和经过后端白名单处理的返回地址，不在浏览器中修改投标状态。
 * 后端会在事务内重新校验 OWNER 身份、任务状态和招标截止时间。
 */
export function WithdrawBidDialog({ bidId, returnTo, taskId }: WithdrawBidDialogProps) {
    const form = useForm({
        // 撤回成功后继续保留详情页原始来源，方便用户返回原筛选结果。
        returnTo,
    });

    /**
     * 向 Laravel 提交撤回请求。
     *
     * preserveScroll 防止验证失败后页面跳回顶部，错误信息由详情页统一展示。
     */
    function withdraw(): void {
        form.post(`/tasks/${taskId}/bids/${bidId}/withdraw`, {
            preserveScroll: true,
        });
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" type="button" variant="outline">
                    撤回投标
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>确认撤回投标</DialogTitle>
                    <DialogDescription>
                        撤回后该方案不能再参与选标，但历史记录会保留。招标截止前可以重新提交新版本。
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button disabled={form.processing} type="button" variant="outline">
                            取消
                        </Button>
                    </DialogClose>
                    <Button
                        className="bg-red-600 text-white hover:bg-red-700"
                        disabled={form.processing}
                        onClick={withdraw}
                        type="button"
                    >
                        {form.processing ? '撤回中...' : '确认撤回'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
