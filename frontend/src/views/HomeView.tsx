import { useMemo } from 'react'
import { Search, Inbox, ListFilter, ArrowDownUp, UserPlus, Wrench, X, RotateCcw } from 'lucide-react'
import { useTasksStore, type SortBy } from '@/stores/tasks'
import { useUserStore } from '@/stores/user'
import { useBidsStore } from '@/stores/bids'
import { MOCK_USERS } from '@/mocks'
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

const SORT_OPTIONS: { key: SortBy; label: string; icon: string }[] = [
  { key: 'default', label: '最近发布', icon: '⏱' },
  { key: 'amount-desc', label: '金额从高到低', icon: '↓' },
  { key: 'amount-asc', label: '金额从低到高', icon: '↑' },
]

function StatCard({ label, value, accent }: { label: string; value: number; accent: string }) {
  return (
    <div className="rounded-lg bg-card px-4 py-3 shadow-[0_1px_2px_rgba(15,23,42,0.04),0_1px_3px_rgba(15,23,42,0.06)]">
      <div className="flex items-center gap-2">
        <span className={`inline-block h-1.5 w-1.5 rounded-full ${accent.split(' ')[0]}`} />
        <span className="text-xs text-muted-foreground">{label}</span>
      </div>
      <div className="text-2xl font-semibold tabular-nums tracking-tight text-foreground mt-1.5">{value}</div>
    </div>
  )
}

export function HomeView() {
  const list = useTasksStore((s) => s.list)
  const currentUser = useUserStore((s) => s.currentUser)
  const bids = useBidsStore((s) => s.bids)
  const filter = useTasksStore((s) => s.filterStatus)
  const complexityFilter = useTasksStore((s) => s.filterComplexity)
  const publisherFilter = useTasksStore((s) => s.filterPublisher)
  const assigneeFilter = useTasksStore((s) => s.filterAssignee)
  const sortBy = useTasksStore((s) => s.sortBy)
  const search = useTasksStore((s) => s.searchQuery)
  const page = useTasksStore((s) => s.page)
  const pageSize = useTasksStore((s) => s.pageSize)
  const setFilter = useTasksStore((s) => s.setFilter)
  const setComplexityFilter = useTasksStore((s) => s.setComplexityFilter)
  const setPublisherFilter = useTasksStore((s) => s.setPublisherFilter)
  const setAssigneeFilter = useTasksStore((s) => s.setAssigneeFilter)
  const setSortBy = useTasksStore((s) => s.setSortBy)
  const setSearch = useTasksStore((s) => s.setSearch)
  const setPage = useTasksStore((s) => s.setPage)
  const resetFilters = useTasksStore((s) => s.resetFilters)

  const publishers = useMemo(
    () => MOCK_USERS.filter((u) => u.role === 'publisher' || u.role === 'boss'),
    [],
  )
  const developers = useMemo(
    () => MOCK_USERS.filter((u) => u.role === 'developer'),
    [],
  )

  // 解析每个任务的接单者（如果有）
  const taskAssigneeMap = useMemo(() => {
    const map: Record<string, string> = {}
    for (const t of list) {
      if (t.assignedBidId) {
        const bid = bids.find((b) => b.id === t.assignedBidId)
        if (bid) map[t.id] = bid.bidderId
      }
    }
    return map
  }, [list, bids])

  const filtered = useMemo(() => {
    let arr = [...list]
    if (filter !== 'all') {
      if (filter === 'FAILED') arr = arr.filter((t) => t.status === 'FAILED' || t.status === 'CANCELLED')
      else arr = arr.filter((t) => t.status === filter)
    }
    if (complexityFilter !== 'all') {
      arr = arr.filter((t) => t.complexity === complexityFilter)
    }
    if (publisherFilter !== 'all') {
      arr = arr.filter((t) => t.createdBy === publisherFilter)
    }
    if (assigneeFilter !== 'all') {
      arr = arr.filter((t) => taskAssigneeMap[t.id] === assigneeFilter)
    }
    const q = search.trim().toLowerCase()
    if (q) {
      arr = arr.filter(
        (t) =>
          t.title.toLowerCase().includes(q) ||
          (t.description || '').toLowerCase().includes(q),
      )
    }
    // 排序
    if (sortBy === 'amount-desc') {
      arr.sort((a, b) => b.budget - a.budget)
    } else if (sortBy === 'amount-asc') {
      arr.sort((a, b) => a.budget - b.budget)
    } else {
      arr.sort((a, b) => b.createdAt.localeCompare(a.createdAt))
    }
    return arr
  }, [list, filter, complexityFilter, publisherFilter, assigneeFilter, sortBy, search, taskAssigneeMap])

  const activeFilterCount =
    (filter !== 'all' ? 1 : 0) +
    (complexityFilter !== 'all' ? 1 : 0) +
    (publisherFilter !== 'all' ? 1 : 0) +
    (assigneeFilter !== 'all' ? 1 : 0) +
    (sortBy !== 'default' ? 1 : 0) +
    (search ? 1 : 0)

  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize))
  const safePage = Math.min(page, totalPages)
  const start = (safePage - 1) * pageSize
  const pageItems = filtered.slice(start, start + pageSize)

  return (
    <div className="mx-auto max-w-7xl px-6 py-8 page-enter">
      {/* Hero 概览卡 */}
      <div className="mb-6 relative overflow-hidden rounded-xl bg-gradient-to-br from-primary/[0.07] via-background to-purple-500/[0.05] p-6 shadow-[0_1px_2px_rgba(15,23,42,0.04),0_1px_3px_rgba(15,23,42,0.06)]">
        <div className="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-primary/10 blur-2xl" />
        <div className="absolute -right-4 bottom-0 h-24 w-24 rounded-full bg-purple-400/10 blur-2xl" />
        <div className="relative flex items-end justify-between flex-wrap gap-4">
          <div>
            <div className="text-[11px] font-semibold uppercase tracking-wider text-primary/70 mb-2">任务大厅</div>
            <h1 className="text-[28px] font-semibold tracking-tight text-foreground">你好、{currentUser?.name || '访客'} 👋</h1>
            <p className="text-sm text-muted-foreground mt-1.5">这里有 <span className="font-semibold text-foreground">{list.filter((t) => t.status === 'OPEN').length}</span> 个新任务可以投标</p>
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="搜索标题或描述..."
                className="h-9 w-60 rounded-md border border-transparent bg-card pl-8 pr-3 text-sm placeholder:text-muted-foreground/70 transition-colors hover:bg-background focus:outline-none focus:bg-background focus:border-primary/50 focus:ring-1 focus:ring-primary/30"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Stats 概览 */}
      <div className="mb-6 grid grid-cols-2 md:grid-cols-4 gap-3">
        <StatCard label="总任务" value={list.length} accent="bg-foreground/10 text-foreground" />
        <StatCard label="招标中" value={list.filter((t) => t.status === 'OPEN').length} accent="bg-blue-500/10 text-blue-700" />
        <StatCard label="进行中" value={list.filter((t) => t.status === 'ASSIGNED').length} accent="bg-orange-500/10 text-orange-700" />
        <StatCard label="已完成" value={list.filter((t) => t.status === 'COMPLETED').length} accent="bg-emerald-500/10 text-emerald-700" />
      </div>

      {/* 筛选 + 排序 工具栏 */}
      <div className="mb-3 flex items-center justify-between flex-wrap gap-3">
        <div className="flex items-center gap-0.5 flex-wrap">
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
        <div className="flex items-center gap-2">
          <span className="text-xs text-muted-foreground flex items-center gap-1.5">
            <ArrowDownUp className="h-3.5 w-3.5" />
            排序
          </span>
          <select
            value={sortBy}
            onChange={(e) => setSortBy(e.target.value as SortBy)}
            className="h-8 rounded-md border border-transparent bg-muted/30 px-2.5 text-sm transition-colors hover:bg-muted/50 focus:outline-none focus:bg-background focus:border-primary/50 focus:ring-1 focus:ring-primary/30"
          >
            {SORT_OPTIONS.map((o) => (
              <option key={o.key} value={o.key}>{o.icon} {o.label}</option>
            ))}
          </select>
        </div>
      </div>

      {/* 复杂度 + 发布者 + 接单者 筛选 */}
      <div className="mb-4 flex items-center gap-x-5 gap-y-2 flex-wrap">
        <div className="flex items-center gap-2 flex-wrap">
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

        <div className="h-4 w-px bg-border/60" />

        <div className="flex items-center gap-2 flex-wrap">
          <span className="text-xs text-muted-foreground flex items-center gap-1.5">
            <UserPlus className="h-3.5 w-3.5" />
            发布者
          </span>
          <select
            value={publisherFilter}
            onChange={(e) => setPublisherFilter(e.target.value)}
            className="h-7 rounded-md border border-transparent bg-muted/30 px-2 text-xs transition-colors hover:bg-muted/50 focus:outline-none focus:bg-background focus:border-primary/50 focus:ring-1 focus:ring-primary/30"
          >
            <option value="all">全部</option>
            {publishers.map((u) => (
              <option key={u.id} value={u.id}>{u.name}</option>
            ))}
          </select>
        </div>

        <div className="h-4 w-px bg-border/60" />

        <div className="flex items-center gap-2 flex-wrap">
          <span className="text-xs text-muted-foreground flex items-center gap-1.5">
            <Wrench className="h-3.5 w-3.5" />
            接单
          </span>
          <select
            value={assigneeFilter}
            onChange={(e) => setAssigneeFilter(e.target.value)}
            className="h-7 rounded-md border border-transparent bg-muted/30 px-2 text-xs transition-colors hover:bg-muted/50 focus:outline-none focus:bg-background focus:border-primary/50 focus:ring-1 focus:ring-primary/30"
          >
            <option value="all">全部</option>
            {developers.map((u) => (
              <option key={u.id} value={u.id}>{u.name}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Active filter chips + result count + reset */}
      <div className="mb-3 flex items-center justify-between flex-wrap gap-2 min-h-[28px]">
        <div className="text-xs text-muted-foreground">
          匹配 <span className="font-semibold text-foreground tabular-nums">{filtered.length}</span> 条
        </div>
        {activeFilterCount > 0 && (
          <button
            onClick={resetFilters}
            className="text-xs text-muted-foreground hover:text-foreground inline-flex items-center gap-1 transition-colors"
          >
            <RotateCcw className="h-3 w-3" />
            清空筛选 ({activeFilterCount})
          </button>
        )}
      </div>

      {/* 任务列表 */}
      {pageItems.length > 0 ? (
        <div className="space-y-2.5">
          {pageItems.map((task) => (
            <TaskCard key={task.id} task={task} />
          ))}
        </div>
      ) : (
        <div className="py-24 text-center">
          <div className="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-muted/40 mb-4">
            <Inbox className="h-7 w-7 text-muted-foreground/40" />
          </div>
          <p className="text-sm font-medium text-foreground">
            {search ? `没找到匹配 “${search}” 的任务` : '当前筛选下没有任务'}
          </p>
          <p className="text-xs text-muted-foreground mt-1">
            {search ? '试试调整搜索词或筛选条件' : '切换一个状态筛选试试'}
          </p>
          {activeFilterCount > 0 && (
            <Button variant="outline" size="sm" className="mt-4" onClick={resetFilters}>
              <X className="h-3.5 w-3.5" />
              清空筛选
            </Button>
          )}
        </div>
      )}

      {/* 分页 */}
      {filtered.length > pageSize && (
        <div className="flex items-center justify-between mt-8">
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