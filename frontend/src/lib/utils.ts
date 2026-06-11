import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * cn() — 智能合并 Tailwind className
 * shadcn/ui 的核心工具
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
