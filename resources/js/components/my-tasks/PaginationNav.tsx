import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import type { PaginationMeta } from '@/types/task';

type PaginationNavProps = {
    links: { next: string | null; prev: string | null };
    meta: PaginationMeta;
};

/**
 * “我的任务”三个列表共用的上一页、下一页导航。
 */
export function PaginationNav({ links, meta }: PaginationNavProps) {
    if (meta.lastPage <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4 text-sm text-[#6e6e80]">
            <span>
                第 {meta.currentPage} / {meta.lastPage} 页，共 {meta.total} 条
            </span>
            <div className="flex gap-2">
                {links.prev ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={links.prev}>上一页</Link>
                    </Button>
                ) : (
                    <Button disabled size="sm" variant="muted">上一页</Button>
                )}
                {links.next ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={links.next}>下一页</Link>
                    </Button>
                ) : (
                    <Button disabled size="sm" variant="muted">下一页</Button>
                )}
            </div>
        </div>
    );
}
