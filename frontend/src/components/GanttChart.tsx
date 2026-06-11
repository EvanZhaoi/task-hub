import { useMemo } from 'react'
import { Link } from 'react-router'
import { formatDate } from '@/utils/format'
import type { Task } from '@/api/types'

interface GanttChartProps {
  tasks: Task[]
  /** 'personal' 模式：用每个用户自己的任务时间范围
   *  'boss' 模式：用全部任务时间范围 */
  mode?: 'personal' | 'boss'
}

const STATUS_BAR_CLASS = {
  OPEN: 'bg-blue-500',
  ASSIGNED: 'bg-orange-500',
  COMPLETED: 'bg-emerald-500',
  FAILED: 'bg-gray-500',
  CANCELLED: 'bg-gray-300 line-through',
  DRAFT: 'bg-gray-400',
} as const

export function GanttChart({ tasks, mode = 'boss' }: GanttChartProps) {
  const { start, end } = useMemo(() => {
    if (tasks.length === 0) {
      const today = new Date()
      return { start: today, end: new Date(today.getTime() + 30 * 86400000) }
    }
    const dates: number[] = []
    for (const t of tasks) {
      dates.push(new Date(t.createdAt).getTime())
      const endDate = t.finalDelivery || t.expectedDelivery || t.createdAt
      dates.push(new Date(endDate).getTime())
    }
    const minD = new Date(Math.min(...dates))
    const maxD = new Date(Math.max(...dates))
    minD.setDate(minD.getDate() - (mode === 'personal' ? 3 : 7))
    maxD.setDate(maxD.getDate() + (mode === 'personal' ? 7 : 14))
    return { start: minD, end: maxD }
  }, [tasks, mode])

  const total = end.getTime() - start.getTime()

  const monthMarkers = useMemo(() => {
    const markers: { label: string; left: number }[] = []
    const cur = new Date(start.getFullYear(), start.getMonth(), 1)
    while (cur <= end) {
      const left = ((cur.getTime() - start.getTime()) / total) * 100
      markers.push({ label: `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}`, left })
      cur.setMonth(cur.getMonth() + 1)
    }
    return markers
  }, [start, end, total])

  if (tasks.length === 0) {
    return (
      <div className="rounded-lg border bg-card p-8 text-center text-muted-foreground text-sm">
        没有任务可以显示
      </div>
    )
  }

  return (
    <div className="rounded-lg border bg-card overflow-x-auto">
      <div className="relative h-6 border-b border-border" style={{ minWidth: 700 }}>
        {monthMarkers.map((m) => (
          <div
            key={m.label}
            className="absolute top-0 bottom-0 border-l border-border text-xs text-muted-foreground pl-1.5"
            style={{ left: `${m.left}%` }}
          >
            {m.label}
          </div>
        ))}
      </div>
      <div className="space-y-1.5 p-3" style={{ minWidth: 700 }}>
        {tasks.map((task) => {
          const tStart = new Date(task.createdAt).getTime()
          const tEndDate = task.finalDelivery || task.expectedDelivery || task.createdAt
          const tEnd = new Date(tEndDate).getTime()
          const left = Math.max(0, ((tStart - start.getTime()) / total) * 100)
          const width = Math.max(mode === 'personal' ? 3 : 2, ((tEnd - tStart) / total) * 100)
          return (
            <div key={task.id} className="flex items-center gap-3 h-8">
              <Link
                to={`/task/${task.id}`}
                className="w-48 text-sm text-foreground hover:text-primary truncate"
                title={task.title}
              >
                {task.title}
              </Link>
              <div className="flex-1 relative h-8 bg-muted/30 rounded">
                <div
                  className={`absolute h-6 rounded ${STATUS_BAR_CLASS[task.status]} flex items-center px-2 text-xs text-white whitespace-nowrap overflow-hidden cursor-pointer transition-transform hover:translate-y-[-1px]`}
                  style={{ left: `${left}%`, width: `${width}%`, top: 4 }}
                  title={`${task.title} · ${formatDate(task.createdAt)} → ${formatDate(tEndDate)}`}
                >
                  <span className="truncate">{task.title}</span>
                </div>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
