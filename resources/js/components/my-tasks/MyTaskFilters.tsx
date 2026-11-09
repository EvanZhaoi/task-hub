import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { PersonalTaskFilters } from '@/types/task';
import { urlWithQuery } from '@/utils/url';

type StatusOption = { label: string; value: string };

type MyTaskFiltersProps = {
    action: string;
    filters: PersonalTaskFilters;
    placeholder: string;
    statusOptions: StatusOption[];
};

/**
 * 三个“我的任务”页面共用的紧凑 GET 查询栏。
 *
 * 各页面传入自己的状态选项和搜索提示，查询字段保持一致但业务含义不会混在一起。
 */
export function MyTaskFilters({ action, filters, placeholder, statusOptions }: MyTaskFiltersProps) {
    return (
        <div className="rounded-lg border border-[#e5e7eb] bg-white p-4 shadow-sm">
            <form action={action} className="flex flex-col gap-3 md:flex-row md:items-center" method="GET">
                {/* 状态由下方快捷项控制，提交关键词查询时通过 hidden 保留当前状态。 */}
                {filters.status !== 'ALL' ? <input name="status" type="hidden" value={filters.status} /> : null}

                <div className="relative min-w-0 flex-1">
                    <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#9ca3af]">
                        搜索
                    </span>
                    <Input
                        className="w-full pl-12"
                        defaultValue={filters.keyword}
                        name="keyword"
                        placeholder={placeholder}
                        type="search"
                    />
                </div>

                <div className="flex items-center gap-2">
                    <Button className="flex-1 md:flex-none" type="submit">
                        查询
                    </Button>
                    <Button asChild className="flex-1 md:flex-none" variant="outline">
                        <Link href={action}>重置</Link>
                    </Button>
                </div>
            </form>

            <div aria-label="状态筛选" className="mt-3 flex flex-wrap gap-2">
                {statusOptions.map((option) => (
                    <Link
                        aria-current={filters.status === option.value ? 'page' : undefined}
                        className={cn(
                            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                            filters.status === option.value
                                ? 'bg-[#f5f3ff] text-[#5e6ad2]'
                                : 'border border-[#e5e7eb] text-[#6e6e80] hover:border-[#c7d2fe] hover:bg-[#fafaff] hover:text-[#4f46e5]',
                        )}
                        href={urlWithQuery(action, {
                            keyword: filters.keyword || null,
                            status: option.value === 'ALL' ? null : option.value,
                        })}
                        key={option.value}
                    >
                        {option.label}
                    </Link>
                ))}
            </div>
        </div>
    );
}
