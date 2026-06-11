import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * cn() - 智能合并 Tailwind className
 * Vue 版同款，逻辑一致
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
