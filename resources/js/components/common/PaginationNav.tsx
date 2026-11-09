import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import type { PaginationMeta } from '@/types/task';

type PaginationNavProps = {
    links: { next: string | null; prev: string | null };
    meta: PaginationMeta;
};

/**
 * 统一列表页分页区域。
 *
 * 组件直接使用 Laravel Paginator 返回的链接，查询条件已经由后端 withQueryString() 保留。
 * 即使只有一页也保持分页区域可见，避免列表数量变化时页面底部布局跳动。
 */
export function PaginationNav({ links, meta }: PaginationNavProps) {
    return (
        <div className="flex flex-col gap-3 rounded-lg border border-[#e5e7eb] bg-white px-4 py-3 text-sm text-[#6e6e80] shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span>
                    显示 {meta.from ?? 0}-{meta.to ?? 0}，共 {meta.total} 条
                </span>
                <span className="text-[#9ca3af]">
                    第 {meta.currentPage} / {meta.lastPage} 页
                </span>
            </div>

            <div className="flex items-center gap-2 self-end sm:self-auto">
                {links.prev ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={links.prev}>
                            <ChevronLeft className="mr-1 size-4" />
                            上一页
                        </Link>
                    </Button>
                ) : (
                    <Button disabled size="sm" variant="muted">
                        <ChevronLeft className="mr-1 size-4" />
                        上一页
                    </Button>
                )}

                {links.next ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={links.next}>
                            下一页
                            <ChevronRight className="ml-1 size-4" />
                        </Link>
                    </Button>
                ) : (
                    <Button disabled size="sm" variant="muted">
                        下一页
                        <ChevronRight className="ml-1 size-4" />
                    </Button>
                )}
            </div>
        </div>
    );
}
