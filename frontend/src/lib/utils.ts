/**
 * cn() 工具函数
 * shadcn-vue 的核心：智能合并 Tailwind className
 *
 * 用法：
 *   <div :class="cn('p-4', isActive && 'bg-blue-500', props.class)" />
 *
 * 原理：
 *   1. clsx 拼接 className（处理条件、数组、对象）
 *   2. tailwind-merge 去重 / 解决冲突（后者覆盖前者）
 */
import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
