import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

// shadcn/ui 组件约定使用 cn() 合并 className。
// clsx 负责条件 class，tailwind-merge 负责解决 Tailwind 冲突，例如 px-2 和 px-4 同时出现时保留后者。
export function cn(...inputs: ClassValue[]): string {
    // 先用 clsx 处理 false/null/undefined/条件对象，再用 twMerge 去掉冲突的 Tailwind class。
    return twMerge(clsx(inputs));
}
