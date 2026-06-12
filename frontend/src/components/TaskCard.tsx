import { Link } from 'react-router'
import { Wallet, Calendar, Clock, Pin, User as UserIcon } from 'lucide-react'
import { formatMoney, formatDate } from '@/utils/format'
import { StatusBadge } from './StatusBadge'
import { ComplexityBadge } from './ComplexityBadge'
import type { Task } from '@/api/types'
import { cn } from '@/lib/utils'

interface TaskCardProps {
  task: Task
}

const STATUS_ACCENT: Record<Task['status'], string> = {
  DRAFT:     'bg-gray-400',
  OPEN:      'bg-blue-500',
  ASSIGNED:  'bg-orange-500',
  COMPLETED: 'bg-emerald-500',
  FAILED:    'bg-gray-500',
  CANCELLED: 'bg-gray-300',
}

export function TaskCard({ task }: TaskCardProps) {
  return (
    <Link
      to={`/task/${task.id}`}
      className={cn(
        'group relative block rounded-lg bg-card pl-5 pr-5 py-5',
        'shadow-[0_1px_2px_rgba(15,23,42,0.04),0_1px_3px_rgba(15,23,42,0.06)]',
        'transition-all duration-200',
        'hover:shadow-[0_8px_20px_rgba(15,23,42,0.08),0_2px_6px_rgba(15,23,42,0.04)]',
        'hover:-translate-y-0.5',
        'overflow-hidden',
      )}
    >
      {/* hover 渐变光晕 */}
      <div className="pointer-events-none absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-gradient-to-br from-primary/[0.03] via-transparent to-transparent" />
      {/* 状态色侧边条 */}
      <span className={cn('absolute left-0 top-3 bottom-3 w-0.5 rounded-r-full', STATUS_ACCENT[task.status])} />
      <div className="flex items-start justify-between gap-4 mb-3">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap mb-1.5">
            <h3 className="text-base font-semibold text-foreground group-hover:text-primary transition-colors">
              {task.title}
            </h3>
            <ComplexityBadge complexity={task.complexity} />
          </div>
          <p className="text-sm text-muted-foreground line-clamp-2 leading-relaxed">
            {stripHtml(task.description)}
          </p>
        </div>
        <StatusBadge status={task.status} />
      </div>

      <div className="flex items-center gap-x-5 gap-y-2 text-xs text-muted-foreground flex-wrap pt-1">
        <Meta icon={Wallet} label="预算" value={formatMoney(task.budget)} highlight />
        <Meta icon={Calendar} label="期望" value={formatDate(task.expectedDelivery)} />
        {task.status === 'OPEN' && task.biddingDeadline && (
          <Meta icon={Clock} label="截止" value={formatDate(task.biddingDeadline)} />
        )}
        {(task.status === 'ASSIGNED' || task.status === 'COMPLETED') && task.finalDelivery && (
          <Meta icon={Pin} label="交期" value={formatDate(task.finalDelivery)} />
        )}
        <span className="ml-auto inline-flex items-center gap-1.5">
          <UserIcon className="h-3 w-3" />
          {userName(task.createdBy)}
        </span>
      </div>
    </Link>
  )
}

function Meta({ icon: Icon, label, value, highlight }: { icon: typeof Wallet; label: string; value: string; highlight?: boolean }) {
  return (
    <span className="inline-flex items-center gap-1.5">
      <Icon className="h-3 w-3" />
      {label}
      <span className={cn('font-medium', highlight ? 'text-foreground' : 'text-foreground/90')}>{value}</span>
    </span>
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