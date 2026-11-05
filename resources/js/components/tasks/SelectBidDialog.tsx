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
import type { BidItem } from '@/types/task';

type SelectBidDialogProps = {
    bid: BidItem;
    taskId: string;
};

/**
 * 发布者选标确认弹窗。
 *
 * 弹窗只提交 URL 中的 taskId 和 bid.id，不把金额、交期或成员重新提交给后端。
 * SelectBidService 会从锁定后的数据库记录读取真实值，避免浏览器篡改中标结果。
 */
export function SelectBidDialog({ bid, taskId }: SelectBidDialogProps) {
    const [open, setOpen] = useState(false);
    const form = useForm<Record<string, never>>({});
    const owner = bid.members.find((member) => member.role === 'OWNER');
    const collaborators = bid.members.filter((member) => member.role === 'COLLABORATOR');

    /**
     * 发送 Inertia POST 请求；成功后关闭弹窗并由详情页新 Props 展示 ASSIGNED 状态。
     */
    function selectBid(): void {
        form.post(`/tasks/${taskId}/bids/${bid.id}/accept`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog onOpenChange={setOpen} open={open}>
            <DialogTrigger asChild>
                <Button size="sm" type="button">
                    <CheckCircle2 className="mr-1.5 size-4" />
                    选择该方案
                </Button>
            </DialogTrigger>

            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>确认选择此投标？</DialogTitle>
                    <DialogDescription>
                        确认后任务将进入进行中，其他有效投标会变为未中标，第一期不支持更换执行成员。
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 rounded-md border border-[#e5e7eb] bg-[#fbfbfc] p-4 text-sm">
                    <ConfirmItem
                        label="主投标人"
                        value={owner ? `${owner.displayName}（${owner.userId}）` : '-'}
                    />
                    <ConfirmItem
                        label="协作成员"
                        value={
                            collaborators.length > 0
                                ? collaborators.map((member) => member.displayName).join('、')
                                : '无'
                        }
                    />
                    <ConfirmItem label="最终金额" strong value={bid.amountLabel} />
                    <ConfirmItem label="最终交期" value={bid.deliveryDate ?? '-'} />
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button disabled={form.processing} type="button" variant="outline">
                            取消
                        </Button>
                    </DialogClose>
                    <Button disabled={form.processing} onClick={selectBid} type="button">
                        {form.processing ? '处理中...' : '确认选标'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type ConfirmItemProps = {
    label: string;
    strong?: boolean;
    value: string;
};

/**
 * 对齐选标确认信息，金额可通过 strong 提高视觉层级。
 */
function ConfirmItem({ label, strong = false, value }: ConfirmItemProps) {
    return (
        <div className="grid grid-cols-[88px_minmax(0,1fr)] items-start gap-3">
            <span className="text-[#6e6e80]">{label}</span>
            <span className={strong ? 'font-semibold text-[#5e6ad2]' : 'font-medium text-[#1f2937]'}>{value}</span>
        </div>
    );
}
