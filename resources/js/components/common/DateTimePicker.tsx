import { Clock } from 'lucide-react';

import { DatePicker } from '@/components/common/DatePicker';
import { Input } from '@/components/ui/input';

type DateTimePickerProps = {
    disabled?: boolean;
    name?: string;
    onChange: (value: string) => void;
    value: string;
};

function splitDateTime(value: string): { date: string; hour: string; minute: string } {
    // 兼容原生 datetime-local 的提交格式：YYYY-MM-DDTHH:mm。
    const match = /^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/.exec(value);

    if (!match) {
        return { date: '', hour: '23', minute: '59' };
    }

    return { date: match[1], hour: match[2], minute: match[3] };
}

function normalizeTimePart(value: string, max: number): string {
    // 时间输入允许用户输入任意小时/分钟；离开输入框时规整到合法范围并补足两位。
    const numeric = Number.parseInt(value, 10);

    if (Number.isNaN(numeric)) {
        return '00';
    }

    return String(Math.min(Math.max(numeric, 0), max)).padStart(2, '0');
}

function joinDateTime(date: string, hour: string, minute: string): string {
    // 日期没选时不提交半成品，避免后端收到只有时间的无效值。
    if (!date) {
        return '';
    }

    return `${date}T${hour}:${minute}`;
}

export function DateTimePicker({ disabled = false, name, onChange, value }: DateTimePickerProps) {
    const { date, hour, minute } = splitDateTime(value);

    function updateDate(nextDate: string): void {
        // 选择日期时保留当前时间；默认时间是 23:59，适合作为招标截止时间。
        onChange(joinDateTime(nextDate, hour, minute));
    }

    function updateHour(nextHour: string): void {
        onChange(joinDateTime(date, normalizeTimePart(nextHour, 23), minute));
    }

    function updateMinute(nextMinute: string): void {
        onChange(joinDateTime(date, hour, normalizeTimePart(nextMinute, 59)));
    }

    return (
        <div className="grid w-full min-w-0 grid-cols-[minmax(0,1fr)_4.5rem_4.5rem] items-center gap-2">
            <DatePicker disabled={disabled} onChange={updateDate} placeholder="请选择日期" value={date} />
            <label className="relative block min-w-0">
                <Clock className="pointer-events-none absolute left-2 top-1/2 size-3.5 -translate-y-1/2 text-[#9ca3af]" />
                <Input
                    className="pl-7 text-center"
                    disabled={disabled}
                    max={23}
                    min={0}
                    onBlur={(event) => updateHour(event.target.value)}
                    onChange={(event) => updateHour(event.target.value)}
                    type="number"
                    value={hour}
                />
            </label>
            <Input
                className="text-center"
                disabled={disabled}
                max={59}
                min={0}
                onBlur={(event) => updateMinute(event.target.value)}
                onChange={(event) => updateMinute(event.target.value)}
                type="number"
                value={minute}
            />

            {/* 隐藏字段保留 name 语义，提交格式保持 YYYY-MM-DDTHH:mm，兼容现有 Laravel 校验。 */}
            {name ? <input name={name} type="hidden" value={value} /> : null}
        </div>
    );
}
