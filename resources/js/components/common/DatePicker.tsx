import { CalendarIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type DatePickerProps = {
    disabled?: boolean;
    name?: string;
    onChange: (value: string) => void;
    placeholder?: string;
    value: string;
};

function parseDate(value: string): Date | undefined {
    // 后端当前接收 YYYY-MM-DD；这里也只解析这个格式，避免浏览器时区把日期偏移一天。
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (!match) {
        return undefined;
    }

    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
}

function formatDate(date: Date): string {
    // 手动拼 YYYY-MM-DD，保持提交格式和原生 input type="date" 一致。
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function displayDate(value: string): string {
    // 页面展示使用中文日期；提交给后端仍然保持 YYYY-MM-DD。
    const date = parseDate(value);

    if (!date) {
        return value;
    }

    return `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日`;
}

export function DatePicker({ disabled = false, name, onChange, placeholder = '请选择日期', value }: DatePickerProps) {
    const selectedDate = parseDate(value);

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    className={cn('w-full justify-start px-3 text-left font-normal', !value && 'text-[#9ca3af]')}
                    disabled={disabled}
                    type="button"
                    variant="outline"
                >
                    <CalendarIcon className="mr-2 size-4 shrink-0" />
                    <span className="min-w-0 truncate">{value ? displayDate(value) : placeholder}</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto">
                <Calendar
                    mode="single"
                    onSelect={(date) => {
                        // 用户清空选择时 date 可能为 undefined；表单值同步清空。
                        onChange(date ? formatDate(date) : '');
                    }}
                    selected={selectedDate}
                />
            </PopoverContent>

            {/* 隐藏字段保留 name 语义，方便以后如果改成原生 form submit 也能提交同名字段。 */}
            {name ? <input name={name} type="hidden" value={value} /> : null}
        </Popover>
    );
}
