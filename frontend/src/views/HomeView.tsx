import { useMemo } from 'react'
import { Search, Inbox, ListFilter } from 'lucide-react'
import { useTasksStore } from '@/stores/tasks'
import { TaskCard } from '@/components/TaskCard'
import { Button } from '@/components/ui/button'
import type { TaskStatus, Complexity } from '@/api/types'
import { complexityLabel } from '@/utils/format'
import { cn } from '@/lib/utils'

const FILTERS: { key: TaskStatus | 'all'; label: string }[] = [
  { key: 'all', label: '全部' },
  { key: 'OPEN', label: '招标中' },
  { key: 'ASSIGNED', label: '进行中' },
  { key: 'COMPLETED', label: '已完成' },
  { key: 'FAILED', label: '流标/取消' },
]

const COMPLEXITIES: Complexity[] = ['LOW', 'MEDIUM', 'HIGH']

export function HomeView() {
  const list = useTasksStore((s) => s.list)
  const filter = useTasksStore((s) => s.filterStatus)
  const complexityFilter = useTasksStore((s) => s.filterComplexity)
  const search = useTasksStore((s) => s.searchQuery)
  const page = useTasksStore((s) => s.page)
  const pageSize = useTasksStore((s) => s.pageSize)
  const setFilter = useTasksStore((s) => s.setFilter)
  const setComplexityFilter = useTasksStore((s) => s.setComplexityFilter)
  const setSearch = useTasksStore((s) => s.setSearch)
  const setPage = useTasksStore((s) => s.setPage)

  const filtered = useMemo(() => {
    let arr = [...list]
    if (filter !== 'all') {
      if (filter === 'FAILED') arr = arr.filter((t) => t.status === 'FAILED' || t.status === 'CANCELLED')
      else arr = arr.filter((t) => t.status === filter)
    }
    if (complexityFilter !== 'all') {
      arr = arr.filter((t) => t.complexity === complexityFilter)
    }
    const q = search.trim().toLowerCase()
    if (q) {
      arr = arr.filter(
        (t) =>
          t.title.toLowerCase().includes(q) ||
          (t.description || '').toLowerCase().includes(q),
      )
    }
    return arr.sort((a, b) => b.createdAt.localeCompare(a.createdAt))
  }, [list, filter, complexityFilter, search])

  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize))
  const safePage = Math.min(page, totalPages)
  const start = (safePage - 1) * pageSize
  const pageItems = filtered.slice(start, start + pageSize)

  return (
    <div className="mx-auto max-w-7xl px-6 py-8 page-enter">
      {/* 页面标题 + 状态/搜索筛选 */}
      <div className="mb-5">
        <div className="flex items-end justify-between flex-wrap gap-3">
          <div>
            <h1 className="text-[26px] font-semibold tracking-tight text-foreground">任务大厅</h1>
            <p className="text-sm text-muted-foreground mt-1">所有公开任务，可投标</p>
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="搜索标题或描述..."
                className="h-9 w-60 rounded-md border border-input bg-background pl-8 pr-3 text-sm placeholder:text-muted-foreground/70 focus:outline-none focus:ring-2 focus:ring-ring/40 focus:border-primary/50 transition-colors"
              />
            </div>
          </div>
        </div>
      </div>

      {/* 状态筛选 */}
      <div className="mb-4 flex items-center gap-0.5">
        {FILTERS.map((f) => (
          <button
            key={f.key}
            onClick={() => setFilter(f.key)}
            className={cn(
              'h-7 px-3 rounded-md text-sm font-medium transition-colors',
              filter === f.key
                ? 'bg-foreground text-background'
                : 'text-muted-foreground hover:text-foreground',
            )}
          >
            {f.label}
          </button>
        ))}
      </div>

      {/* 复杂度筛选 */}
      <div className="mb-6 flex items-center gap-2 flex-wrap">
        <span className="text-xs text-muted-foreground flex items-center gap-1.5">
          <ListFilter className="h-3.5 w-3.5" />
          复杂度
        </span>
        <button
          onClick={() => setComplexityFilter('all')}
          className={cn(
            'h-7 px-3 rounded-full text-xs font-medium transition-colors',
            complexityFilter === 'all'
              ? 'bg-foreground text-background'
              : 'bg-muted/50 text-muted-foreground hover:text-foreground',
          )}
        >
          全部
        </button>
        {COMPLEXITIES.map((c) => {
          const active = complexityFilter === c
          return (
            <button
              key={c}
              onClick={() => setComplexityFilter(c)}
              className={cn(
                'badge transition-opacity',
                active ? c === 'LOW' ? 'badge-low' : c === 'HIGH' ? 'badge-high' : 'badge-medium'
                       : 'opacity-50 hover:opacity-80',
              )}
            >
              {complexityLabel[c]}
            </button>
          )
        })}
      </div>

      {/* 任务列表 */}
      {pageItems.length > 0 ? (
        <div className="space-y-2">
          {pageItems.map((task) => (
            <TaskCard key={task.id} task={task} />
          ))}
        </div>
      ) : (
        <div className="py-20 text-center">
          <Inbox className="h-10 w-10 mx-auto text-muted-foreground/30 mb-3" />
          <p className="text-sm text-muted-foreground">
            {search ? `没找到匹配 “${search}” 的任务` : '当前筛选下没有任务'}
          </p>
          {search && (
            <Button variant="outline" size="sm" className="mt-3" onClick={() => setSearch('')}>
              清空搜索
            </Button>
          )}
        </div>
      )}

      {/* 分页 */}
      {filtered.length > pageSize && (
        <div className="flex items-center justify-between mt-6 pt-4 border-t border-border">
          <span className="text-sm text-muted-foreground">
            显示 {start + 1}-{Math.min(start + pageSize, filtered.length)} / 共 {filtered.length} 条
          </span>
          <div className="flex items-center gap-2">
            <Button size="sm" variant="outline" disabled={safePage === 1} onClick={() => setPage(safePage - 1)}>
              ‹ 上一页
            </Button>
            <span className="text-sm text-muted-foreground px-2">第 {safePage} / {totalPages} 页</span>
            <Button
              size="sm"
              variant="outline"
              disabled={safePage === totalPages}
              onClick={() => setPage(safePage + 1)}
            >
              下一页 ›
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}