import { Search, UserPlus, X } from 'lucide-react';
import { useEffect, useMemo, useState, type FocusEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { formatDepartmentName } from '@/utils/personnel';
import type { PersonnelOption } from '@/types/task';

const MAX_VISIBLE_OPTIONS = 20;
const SEARCH_DEBOUNCE_MS = 300;

type PersonnelMultiSelectProps = {
    disabled?: boolean;
    excludedEmployeeNos?: string[];
    maxSelections?: number;
    onChange: (employeeNos: string[]) => void;
    options: PersonnelOption[];
    placeholder?: string;
    selectionLabel?: string;
    value: string[];
};

/**
 * 延迟返回搜索词，避免人员数量较多时每次键盘输入都立即过滤完整数组。
 */
function useDebouncedValue(value: string, delayMs: number): string {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebouncedValue(value), delayMs);

        return () => window.clearTimeout(timer);
    }, [delayMs, value]);

    return debouncedValue;
}

/**
 * 判断人员选项是否匹配工号、姓名或部门关键字。
 */
function optionMatches(option: PersonnelOption, keyword: string): boolean {
    return [option.employeeNo, option.displayName, option.departmentName]
        .filter((part): part is string => typeof part === 'string' && part.length > 0)
        .join(' ')
        .toLowerCase()
        .includes(keyword);
}

/**
 * 多人员选择器。
 *
 * 组件只处理人员搜索和已选工号，不包含投标业务规则，因此后续直接指派也可以复用。
 * 候选列表直接渲染在表单滚动区域内，避免 Dialog 与 Portal 之间的焦点和层级冲突。
 */
export function PersonnelMultiSelect({
    disabled = false,
    excludedEmployeeNos = [],
    maxSelections,
    onChange,
    options,
    placeholder = '按姓名、工号或部门搜索',
    selectionLabel = '成员',
    value,
}: PersonnelMultiSelectProps) {
    const [keyword, setKeyword] = useState('');
    const [showOptions, setShowOptions] = useState(false);
    const debouncedKeyword = useDebouncedValue(keyword, SEARCH_DEBOUNCE_MS).trim().toLowerCase();
    const excludedSet = useMemo(() => new Set(excludedEmployeeNos), [excludedEmployeeNos]);
    const selectedSet = useMemo(() => new Set(value), [value]);
    const optionMap = useMemo(
        () => new Map(options.map((option) => [option.employeeNo, option])),
        [options],
    );
    const filteredOptions = useMemo(
        () =>
            options
                .filter((option) => !excludedSet.has(option.employeeNo) && !selectedSet.has(option.employeeNo))
                .filter((option) => debouncedKeyword === '' || optionMatches(option, debouncedKeyword))
                .slice(0, MAX_VISIBLE_OPTIONS),
        [debouncedKeyword, excludedSet, options, selectedSet],
    );

    /**
     * 将人员加入已选工号数组，同时清空搜索词方便继续选择下一人。
     */
    function addEmployee(employeeNo: string): void {
        if (
            selectedSet.has(employeeNo)
            || excludedSet.has(employeeNo)
            || (maxSelections !== undefined && value.length >= maxSelections)
        ) {
            return;
        }

        const nextValue = [...value, employeeNo];

        onChange(nextValue);
        setKeyword('');

        // 单选场景选中负责人后立即收起候选区，避免候选列表残留在表单中。
        if (maxSelections !== undefined && nextValue.length >= maxSelections) {
            setShowOptions(false);
        }
    }

    /**
     * 从已选工号数组中移除指定人员。
     */
    function removeEmployee(employeeNo: string): void {
        onChange(value.filter((selectedEmployeeNo) => selectedEmployeeNo !== employeeNo));
    }

    /**
     * 焦点离开整个组件后关闭候选区；组件内部按钮切换焦点时保持展开。
     */
    function handleBlur(event: FocusEvent<HTMLDivElement>): void {
        if (!event.currentTarget.contains(event.relatedTarget)) {
            setShowOptions(false);
        }
    }

    return (
        <div className="space-y-2" onBlur={handleBlur}>
            {value.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {value.map((employeeNo) => {
                        const option = optionMap.get(employeeNo);

                        return (
                            <span
                                className="inline-flex max-w-full items-center gap-1.5 rounded-md bg-[#f5f3ff] px-2.5 py-1 text-sm text-[#4f46a5]"
                                key={employeeNo}
                            >
                                <span className="max-w-52 truncate">
                                    {option?.displayName ?? employeeNo}（{employeeNo}）
                                </span>
                                <button
                                    aria-label={`移除${selectionLabel} ${option?.displayName ?? employeeNo}`}
                                    className="rounded-sm p-0.5 hover:bg-[#e9e5ff]"
                                    disabled={disabled}
                                    onClick={() => removeEmployee(employeeNo)}
                                    type="button"
                                >
                                    <X className="size-3.5" />
                                </button>
                            </span>
                        );
                    })}
                </div>
            ) : null}

            {maxSelections === undefined || value.length < maxSelections ? (
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" />
                    <Input
                        className="pl-9"
                        disabled={disabled || options.length === 0}
                        onChange={(event) => setKeyword(event.target.value)}
                        onFocus={() => setShowOptions(true)}
                        placeholder={options.length === 0 ? '人员列表暂不可用' : placeholder}
                        type="search"
                        value={keyword}
                    />
                </div>
            ) : null}

            {showOptions && !disabled && (maxSelections === undefined || value.length < maxSelections) ? (
                <div className="max-h-52 overflow-y-auto rounded-md border border-[#e5e7eb] bg-white p-1 shadow-sm">
                    {filteredOptions.length === 0 ? (
                        <div className="px-3 py-5 text-center text-sm text-[#9ca3af]">没有可选择的人员</div>
                    ) : (
                        filteredOptions.map((option) => (
                            <Button
                                className={cn(
                                    'h-auto w-full justify-start gap-3 px-3 py-2 text-left font-normal',
                                    'hover:bg-[#f5f3ff] hover:text-[#1a1a1a]',
                                )}
                                key={option.employeeNo}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => addEmployee(option.employeeNo)}
                                type="button"
                                variant="ghost"
                            >
                                <UserPlus className="size-4 shrink-0 text-[#6e6ad2]" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium">{option.displayName}</span>
                                    <span className="block truncate text-xs text-[#6e6e80]">
                                        {option.employeeNo}
                                        {option.departmentName
                                            ? ` · ${formatDepartmentName(option.departmentName)}`
                                            : ''}
                                    </span>
                                </span>
                            </Button>
                        ))
                    )}
                </div>
            ) : null}
        </div>
    );
}
