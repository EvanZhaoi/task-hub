import { useEffect, useState } from 'react';

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
    const [hourInput, setHourInput] = useState(hour);
    const [minuteInput, setMinuteInput] = useState(minute);

    useEffect(() => {
        // 父组件重置表单或从后端回填数据时，同步刷新输入框里的显示值。
        setHourInput(hour);
        setMinuteInput(minute);
    }, [hour, minute]);

    function updateDate(nextDate: string): void {
        // 选择日期时保留当前时间；默认时间是 23:59，适合作为招标截止时间。
        onChange(joinDateTime(nextDate, normalizeTimePart(hourInput, 23), normalizeTimePart(minuteInput, 59)));
    }

    function updateHour(nextHour: string): void {
        // 输入过程中只允许 0-2 位数字，不立即补 0。
        // 这样用户可以自然输入 2、23，而不会刚输入 2 就被强制变成 02。
        if (!/^\d{0,2}$/.test(nextHour)) {
            return;
        }

        setHourInput(nextHour);
    }

    function commitHour(nextHour: string): void {
        const normalizedHour = normalizeTimePart(nextHour, 23);

        setHourInput(normalizedHour);
        onChange(joinDateTime(date, normalizedHour, normalizeTimePart(minuteInput, 59)));
    }

    function updateMinute(nextMinute: string): void {
        // 分钟支持任意 0-59，不限制 5 分钟或 15 分钟间隔。
        if (!/^\d{0,2}$/.test(nextMinute)) {
            return;
        }

        setMinuteInput(nextMinute);
    }

    function commitMinute(nextMinute: string): void {
        const normalizedMinute = normalizeTimePart(nextMinute, 59);

        setMinuteInput(normalizedMinute);
        onChange(joinDateTime(date, normalizeTimePart(hourInput, 23), normalizedMinute));
    }

    return (
        <div className="grid w-full min-w-0 grid-cols-[minmax(0,1fr)_4.25rem_4.25rem] items-center gap-2 sm:grid-cols-[minmax(0,1fr)_5rem_5rem]">
            <DatePicker disabled={disabled} onChange={updateDate} placeholder="请选择日期" value={date} />
            <Input
                aria-label="小时"
                className="px-2 text-center tabular-nums"
                disabled={disabled}
                inputMode="numeric"
                maxLength={2}
                onBlur={(event) => commitHour(event.target.value)}
                onChange={(event) => updateHour(event.target.value)}
                placeholder="时"
                type="text"
                value={hourInput}
            />
            <Input
                aria-label="分钟"
                className="px-2 text-center tabular-nums"
                disabled={disabled}
                inputMode="numeric"
                maxLength={2}
                onBlur={(event) => commitMinute(event.target.value)}
                onChange={(event) => updateMinute(event.target.value)}
                placeholder="分"
                type="text"
                value={minuteInput}
            />

            {/* 隐藏字段保留 name 语义，提交格式保持 YYYY-MM-DDTHH:mm，兼容现有 Laravel 校验。 */}
            {name ? <input name={name} type="hidden" value={value} /> : null}
        </div>
    );
}
