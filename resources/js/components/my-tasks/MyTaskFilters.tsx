import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import type { PersonalTaskFilters } from '@/types/task';

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
        <form action={action} className="grid gap-3 rounded-lg border border-[#e5e7eb] bg-white p-4 md:grid-cols-[minmax(240px,1fr)_200px_auto]" method="GET">
            <Input defaultValue={filters.keyword} name="keyword" placeholder={placeholder} />
            <NativeSelect className="w-full" defaultValue={filters.status} name="status">
                {statusOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </NativeSelect>
            <div className="flex items-center gap-2">
                <Button className="flex-1 md:flex-none" type="submit">
                    查询
                </Button>
                <Button asChild className="flex-1 md:flex-none" variant="outline">
                    <Link href={action}>重置</Link>
                </Button>
            </div>
        </form>
    );
}
