import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { Branch as DismissableLayerBranch } from '@radix-ui/react-dismissable-layer';
import * as PopoverPrimitive from '@radix-ui/react-popover';
import { useEffect, useMemo, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { PaymentAccountOption } from '@/types/task';

const MAX_VISIBLE_OPTIONS = 20;
const SEARCH_DEBOUNCE_MS = 300;

type PaymentAccountComboboxProps = {
    disabled?: boolean;
    name?: string;
    onChange: (value: string) => void;
    options: PaymentAccountOption[];
    value: string;
};

function normalizeKeyword(value: string): string {
    // 搜索时统一转小写并去掉首尾空格，保证 code / 名称的模糊匹配更宽容。
    return value.trim().toLowerCase();
}

function optionMatches(option: PaymentAccountOption, keyword: string): boolean {
    // 用户可以按账号名称、账号 code 或完整 label 搜索。
    // 后端仍然只接收 option.value，也就是 payment_account_id。
    const searchableText = [option.label, option.value, option.accountName, option.departmentName]
        .filter((part): part is string => typeof part === 'string' && part.length > 0)
        .join(' ')
        .toLowerCase();

    return searchableText.includes(keyword);
}

function useDebouncedValue(value: string, delayMs: number): string {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        // 账号列表数据量可能很大，不能每按一个键就立即过滤完整列表。
        // 这里延迟约 300ms，等用户暂停输入后再更新搜索关键字。
        const timer = window.setTimeout(() => setDebouncedValue(value), delayMs);

        return () => window.clearTimeout(timer);
    }, [delayMs, value]);

    return debouncedValue;
}

export function PaymentAccountCombobox({
    disabled = false,
    name,
    onChange,
    options,
    value,
}: PaymentAccountComboboxProps) {
    const [open, setOpen] = useState(false);
    const [keyword, setKeyword] = useState('');
    const searchInputRef = useRef<HTMLInputElement>(null);
    const selectedOption = options.find((option) => option.value === value);
    const debouncedKeyword = useDebouncedValue(keyword, SEARCH_DEBOUNCE_MS);
    const normalizedKeyword = normalizeKeyword(debouncedKeyword);
    const filteredOptions = useMemo(
        () => {
            // 默认只展示前 20 条，避免弹窗打开时一次性渲染几百/几千个账号节点。
            if (normalizedKeyword.length === 0) {
                return options.slice(0, MAX_VISIBLE_OPTIONS);
            }

            // 搜索时仍然只取前 20 个匹配项；数据源当前来自后端/Redis 一次性下发列表。
            // 如果未来外部接口支持 name/code 后端查询，再把这里替换成远程搜索。
            return options.filter((option) => optionMatches(option, normalizedKeyword)).slice(0, MAX_VISIBLE_OPTIONS);
        },
        [normalizedKeyword, options],
    );

    function changeOpen(nextOpen: boolean): void {
        // 每次关闭浮层时清空搜索词，避免下次打开还停留在上一次过滤后的结果。
        setOpen(nextOpen);

        if (!nextOpen) {
            setKeyword('');
        }
    }

    function selectOption(nextValue: string): void {
        // 选择后只把账号 ID 写回表单；账号名称、部门等展示信息由后端再次查询并生成快照。
        onChange(nextValue);
        changeOpen(false);
    }

    useEffect(() => {
        if (!open) {
            return;
        }

        // 下拉内容通过 Portal 渲染到 Dialog 外层后，需要主动把焦点放回搜索框。
        // 这样用户打开付款账号选择器后可以直接输入关键字筛选。
        window.requestAnimationFrame(() => searchInputRef.current?.focus());
    }, [open]);

    return (
        <Popover onOpenChange={changeOpen} open={open}>
            <PopoverTrigger asChild>
                <Button
                    className={cn(
                        'w-full min-w-0 justify-between px-3 text-left font-normal',
                        !selectedOption && 'text-[#9ca3af]',
                    )}
                    disabled={disabled}
                    type="button"
                    variant="outline"
                >
                    <span className="min-w-0 truncate">
                        {selectedOption ? selectedOption.label : '请选择付款账号'}
                    </span>
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 text-[#9ca3af]" />
                </Button>
            </PopoverTrigger>

            <PopoverPrimitive.Portal>
                <DismissableLayerBranch>
                    <PopoverPrimitive.Content
                        align="start"
                        className="pointer-events-auto z-[80] w-[var(--radix-popover-trigger-width)] rounded-md border border-[#e5e7eb] bg-white p-0 text-[#1a1a1a] shadow-lg outline-none"
                        sideOffset={8}
                    >
                        <div className="border-b border-[#e5e7eb] p-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" />
                                <Input
                                    // 搜索框只负责缩小候选范围，不直接修改表单提交值。
                                    className="pl-9"
                                    onChange={(event) => setKeyword(event.target.value)}
                                    placeholder="搜索账号名称或 code"
                                    ref={searchInputRef}
                                    type="search"
                                    value={keyword}
                                />
                            </div>
                        </div>

                        <div className="max-h-72 overflow-y-auto p-1">
                            {filteredOptions.length === 0 ? (
                                <div className="px-3 py-6 text-center text-sm text-[#9ca3af]">
                                    没有匹配的付款账号
                                </div>
                            ) : (
                                filteredOptions.map((option) => {
                                    const isSelected = option.value === value;

                                    return (
                                        <button
                                            className={cn(
                                                'flex w-full cursor-pointer items-start gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-[#f5f3ff]',
                                                isSelected && 'bg-[#f5f3ff] text-[#5e6ad2]',
                                            )}
                                            key={option.value}
                                            onClick={() => selectOption(option.value)}
                                            type="button"
                                        >
                                            <Check
                                                className={cn(
                                                    'mt-0.5 size-4 shrink-0',
                                                    isSelected ? 'opacity-100' : 'opacity-0',
                                                )}
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">
                                                    {option.accountName ?? option.label}
                                                </span>
                                                <span className="block truncate text-xs text-[#6e6e80]">
                                                    {option.value}
                                                    {option.departmentName ? ` · ${option.departmentName}` : ''}
                                                </span>
                                            </span>
                                        </button>
                                    );
                                })
                            )}
                        </div>
                    </PopoverPrimitive.Content>
                </DismissableLayerBranch>
            </PopoverPrimitive.Portal>

            {/* 隐藏字段保留表单字段名；最终提交给 Laravel 的仍然是 paymentAccountId。 */}
            {name ? <input name={name} type="hidden" value={value} /> : null}
        </Popover>
    );
}
