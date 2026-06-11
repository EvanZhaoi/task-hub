/**
 * 格式化工具
 */
import dayjs from 'dayjs'
import type { TaskStatus, BidStatus, ChangeType, UserRole } from '@/api/types'

// 金额
export function formatMoney(amount?: number): string {
  if (amount === undefined || amount === null) return '—'
  return `¥${amount.toLocaleString('zh-CN')}`
}

// 文件大小
export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

// 日期
export function formatDate(date?: string): string {
  if (!date) return '—'
  return dayjs(date).format('YYYY-MM-DD')
}

export function formatDateTime(date?: string): string {
  if (!date) return '—'
  return dayjs(date).format('YYYY-MM-DD HH:mm')
}

// 相对时间（如 "2 小时前"）
export function formatRelative(date?: string): string {
  if (!date) return '—'
  const target = dayjs(date)
  const now = dayjs()
  const diff = now.diff(target, 'minute')
  if (diff < 1) return '刚刚'
  if (diff < 60) return `${diff} 分钟前`
  if (diff < 60 * 24) return `${Math.floor(diff / 60)} 小时前`
  if (diff < 60 * 24 * 7) return `${Math.floor(diff / 60 / 24)} 天前`
  return target.format('YYYY-MM-DD')
}

// 状态文案
export const taskStatusLabel: Record<TaskStatus, string> = {
  DRAFT: '草稿',
  OPEN: '招标中',
  ASSIGNED: '进行中',
  COMPLETED: '已完成',
  FAILED: '流标',
  CANCELLED: '已取消',
}

export const bidStatusLabel: Record<BidStatus, string> = {
  ACTIVE: '投标中',
  WITHDRAWN: '已撤回',
  ACCEPTED: '已选中',
  LOST: '未中',
}

export const changeTypeLabel: Record<ChangeType, string> = {
  AMOUNT: '金额变更',
  DELIVERY: '交期变更',
  DESCRIPTION: '描述变更',
  EXTENSION: '交期延期',
  CANCEL: '任务取消',
}

export const roleLabel: Record<UserRole, string> = {
  developer: '开发者',
  publisher: '发布者',
  boss: '老板',
}
