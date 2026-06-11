import dayjs from 'dayjs'
import type { TaskStatus, BidStatus, UserRole } from '@/api/types'

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

export const taskStatusLabel: Record<TaskStatus, string> = {
  DRAFT: '草稿', OPEN: '招标中', ASSIGNED: '进行中',
  COMPLETED: '已完成', FAILED: '流标', CANCELLED: '已取消',
}

export const bidStatusLabel: Record<BidStatus, string> = {
  ACTIVE: '投标中', WITHDRAWN: '已撤回', ACCEPTED: '已选中', LOST: '未中',
}

export const roleLabel: Record<UserRole, string> = {
  developer: '开发者', publisher: '发布者', boss: '老板',
}
