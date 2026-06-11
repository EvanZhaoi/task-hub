import { taskStatusLabel, bidStatusLabel } from '@/utils/format'
import type { TaskStatus, BidStatus } from '@/api/types'

interface StatusBadgeProps {
  status: TaskStatus | BidStatus
  type?: 'task' | 'bid'
}

export function StatusBadge({ status, type = 'task' }: StatusBadgeProps) {
  if (type === 'bid') {
    return <span className={`badge bid-${status.toLowerCase()}`}>{bidStatusLabel[status as BidStatus]}</span>
  }
  return <span className={`badge badge-${status.toLowerCase()}`}>{taskStatusLabel[status as TaskStatus]}</span>
}
