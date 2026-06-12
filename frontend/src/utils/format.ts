import dayjs from 'dayjs'
import type { TaskStatus, BidStatus, UserRole, Complexity } from '@/api/types'

export const formatMoney = (amount?: number) =>
  amount === undefined || amount === null ? '—' : `¥${amount.toLocaleString('zh-CN')}`

export const formatDate = (date?: string) =>
  date ? dayjs(date).format('YYYY-MM-DD') : '—'

export const formatDateTime = (date?: string) =>
  date ? dayjs(date).format('YYYY-MM-DD HH:mm') : '—'

export const formatRelative = (date?: string) => {
  if (!date) return '—'
  const diff = dayjs().diff(dayjs(date), 'minute')
  if (diff < 1) return '刚刚'
  if (diff < 60) return `${diff} 分钟前`
  if (diff < 60 * 24) return `${Math.floor(diff / 60)} 小时前`
  if (diff < 60 * 24 * 7) return `${Math.floor(diff / 60 / 24)} 天前`
  return dayjs(date).format('YYYY-MM-DD')
}

export const fileSizeFmt = (bytes: number): string => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

export const taskStatusLabel: Record<TaskStatus, string> = {
  DRAFT: '草稿', OPEN: '招标中', ASSIGNED: '进行中',
  COMPLETED: '已完成', FAILED: '流标', CANCELLED: '已取消',
}

export const complexityLabel: Record<Complexity, string> = {
  LOW: '简单', MEDIUM: '中等复杂', HIGH: '高度复杂',
}

export const bidStatusLabel: Record<BidStatus, string> = {
  ACTIVE: '投标中', WITHDRAWN: '已撤回', ACCEPTED: '已中标', LOST: '未中',
}

export const changeTypeLabel = {
  AMOUNT: '金额变更',
  DELIVERY: '交期变更',
  DESCRIPTION: '描述变更',
  EXTENSION: '交期延期',
  CANCEL: '任务取消',
} as const

export const roleLabel: Record<UserRole, string> = {
  developer: '开发者', publisher: '发布者', boss: '老板',
}
