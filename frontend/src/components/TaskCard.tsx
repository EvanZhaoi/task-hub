import { Link } from 'react-router'
import { formatMoney, formatDate } from '@/utils/format'
import { StatusBadge } from './StatusBadge'
import { DifficultyBadge } from './DifficultyBadge'
import type { Task } from '@/api/types'

interface TaskCardProps {
  task: Task
}

export function TaskCard({ task }: TaskCardProps) {
  return (
    <Link
      to={`/task/${task.id}`}
      className="block rounded-lg border bg-card p-5 transition-shadow hover:border-foreground/20 hover:shadow-sm"
    >
      <div className="flex items-start justify-between gap-4 mb-2">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <h3 className="text-base font-medium text-foreground">{task.title}</h3>
            <DifficultyBadge difficulty={task.difficulty} />
          </div>
          <p className="text-sm text-muted-foreground mt-1 line-clamp-2">
            {stripHtml(task.description)}
          </p>
        </div>
        <StatusBadge status={task.status} />
      </div>
      <div className="flex items-center gap-5 text-xs text-muted-foreground mt-3 flex-wrap">
        <span>💰 预算 <span className="text-foreground font-medium">{formatMoney(task.budget)}</span></span>
        <span>📅 期望 <span className="text-foreground">{formatDate(task.expectedDelivery)}</span></span>
        {task.status === 'OPEN' && (
          <span>⏰ 招标截止 <span className="text-foreground">{formatDate(task.biddingDeadline)}</span></span>
        )}
        {(task.status === 'ASSIGNED' || task.status === 'COMPLETED') && task.finalDelivery && (
          <span>📌 交期 <span className="text-foreground">{formatDate(task.finalDelivery)}</span></span>
        )}
        <span className="ml-auto">👤 {userName(task.createdBy)}</span>
      </div>
    </Link>
  )
}

function userName(id: string): string {
  const map: Record<string, string> = { u1: '李雷', u2: '韩梅梅', u3: '王运维', u4: '张总', u5: '陈PM', u6: 'Evan' }
  return map[id] || '未知'
}

function stripHtml(html?: string): string {
  if (!html) return ''
  return html.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim()
}
