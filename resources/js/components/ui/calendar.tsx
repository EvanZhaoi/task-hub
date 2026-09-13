import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ComponentProps } from 'react';
import { DayPicker } from 'react-day-picker';
import type { DayButtonProps, NextMonthButtonProps, PreviousMonthButtonProps } from 'react-day-picker';

import { cn } from '@/lib/utils';

type CalendarProps = ComponentProps<typeof DayPicker>;

export function Calendar({ className, classNames, showOutsideDays = true, ...props }: CalendarProps) {
    return (
        <DayPicker
            className={cn('p-0 text-sm', className)}
            classNames={{
                // month_grid/table/row/cell 是 react-day-picker v10 的结构类名。
                // 这里直接把视觉样式集中在基础 Calendar 中，业务组件只负责传 selected/onSelect。
                months: 'flex flex-col',
                month: 'space-y-3',
                month_caption: 'relative flex items-center justify-center',
                caption_label: 'text-sm font-medium',
                nav: 'absolute inset-x-0 top-0 flex items-center justify-between',
                button_previous:
                    'inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-[#6e6e80] hover:bg-[#f3f4f6] disabled:cursor-not-allowed disabled:opacity-40',
                button_next:
                    'inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-[#6e6e80] hover:bg-[#f3f4f6] disabled:cursor-not-allowed disabled:opacity-40',
                month_grid: 'w-full border-collapse space-y-1',
                weekdays: 'flex',
                weekday: 'w-9 rounded-md text-center text-xs font-normal text-[#9ca3af]',
                week: 'mt-1 flex w-full',
                day: 'size-9 p-0 text-center align-middle',
                day_button:
                    'inline-flex size-9 cursor-pointer items-center justify-center rounded-md text-sm hover:bg-[#f5f3ff] hover:text-[#5e6ad2] focus:bg-[#f5f3ff] focus:text-[#5e6ad2] disabled:cursor-not-allowed disabled:opacity-40',
                selected:
                    '[&>button]:bg-[#5e6ad2] [&>button]:text-white [&>button]:hover:bg-[#4f5bd5] [&>button]:hover:text-white',
                today: '[&>button]:border [&>button]:border-[#5e6ad2]/40',
                outside: 'text-[#c4c4cc] opacity-60',
                disabled: 'text-[#c4c4cc] opacity-50',
                hidden: 'invisible',
                ...classNames,
            }}
            components={{
                // 使用 lucide 图标替代默认箭头，保持项目图标风格统一。
                Chevron: ({ orientation }) =>
                    orientation === 'left' ? <ChevronLeft className="size-4" /> : <ChevronRight className="size-4" />,
                DayButton: ({ day: _day, modifiers: _modifiers, ...buttonProps }: DayButtonProps) => (
                    // 日历经常放在 form 里；button 默认 type 是 submit。
                    // 如果不显式写 type="button"，点击日期可能会提交发布任务表单，导致“点了日期但没有选中”的错觉。
                    <button {...buttonProps} type="button" />
                ),
                NextMonthButton: (buttonProps: NextMonthButtonProps) => (
                    // 月份切换按钮也在 form 内部，必须避免默认 submit 行为。
                    <button {...buttonProps} type="button" />
                ),
                PreviousMonthButton: (buttonProps: PreviousMonthButtonProps) => (
                    // 月份切换只改变日历视图，不应该提交发布任务表单。
                    <button {...buttonProps} type="button" />
                ),
            }}
            showOutsideDays={showOutsideDays}
            {...props}
        />
    );
}
