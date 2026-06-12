import { useMemo } from 'react'
import { useTasksStore } from '@/stores/tasks'
import { TaskCard } from '@/components/TaskCard'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { TaskStatus, Difficulty } from '@/api/types'
import { difficultyLabel } from '@/utils/format'

const FILTERS: { key: TaskStatus | 'all'; label: string }[] = [
  { key: 'all', label: '全部' },
  { key: 'OPEN', label: '招标中' },
  { key: 'ASSIGNED', label: '进行中' },
  { key: 'COMPLETED', label: '已完成' },
  { key: 'FAILED', label: '流标/取消' },
]

const DIFFICULTIES: Difficulty[] = ['EASY', 'NORMAL', 'HARD']

export function HomeView() {
  const list = useTasksStore((s) => s.list)
  const filter = useTasksStore((s) => s.filterStatus)
  const diffFilter = useTasksStore((s) => s.filterDifficulty)
  const search = useTasksStore((s) => s.searchQuery)
  const page = useTasksStore((s) => s.page)
  const pageSize = useTasksStore((s) => s.pageSize)
  const setFilter = useTasksStore((s) => s.setFilter)
  const setDiffFilter = useTasksStore((s) => s.setDifficultyFilter)
  const setSearch = useTasksStore((s) => s.setSearch)
  const setPage = useTasksStore((s) => s.setPage)

  const filtered = useMemo(() => {
    let arr = [...list]
    if (filter !== 'all') {
      if (filter === 'FAILED') arr = arr.filter((t) => t.status === 'FAILED' || t.status === 'CANCELLED')
      else arr = arr.filter((t) => t.status === filter)
    }
    if (diffFilter !== 'all') {
      arr = arr.filter((t) => t.difficulty === diffFilter)
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
  }, [list, filter, diffFilter, search])

  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize))
  const safePage = Math.min(page, totalPages)
  const start = (safePage - 1) * pageSize
  const pageItems = filtered.slice(start, start + pageSize)

  return (
    <div className="mx-auto max-w-7xl px-6 py-8">
      <div className="mb-6 flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-foreground">任务大厅</h1>
          <p className="text-sm text-muted-foreground mt-1">所有公开任务，可投标</p>
        </div>
        <div className="flex items-center gap-3 flex-wrap">
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="搜索标题或描述..."
            className="w-60"
          />
          <div className="flex gap-1">
            {FILTERS.map((f) => (
              <Button
                key={f.key}
                size="sm"
                variant={filter === f.key ? 'default' : 'outline'}
                onClick={() => setFilter(f.key)}
              >
                {f.label}
              </Button>
            ))}
          </div>
        </div>
      </div>
      <div className="mb-4 flex items-center gap-2 flex-wrap">
        <span className="text-xs text-muted-foreground">难度：</span>
        <button
          onClick={() => setDiffFilter('all')}
          className={`text-xs px-2.5 py-1 rounded-full border transition-colors ${
            diffFilter === 'all'
              ? 'border-foreground bg-foreground text-background'
              : 'border-border bg-background hover:bg-accent'
          }`}
        >
          全部
        </button>
        {DIFFICULTIES.map((d) => (
          <button
            key={d}
            onClick={() => setDiffFilter(d)}
            className={`badge border ${
              diffFilter === d
                ? d === 'EASY' ? 'badge-easy border-green-300 ring-1 ring-green-300'
                  : d === 'HARD' ? 'badge-hard border-red-300 ring-1 ring-red-300'
                  : 'badge-normal border-gray-300 ring-1 ring-gray-300'
                : 'opacity-60 hover:opacity-100 border-transparent'
            }`}
          >
            {difficultyLabel[d]}
          </button>
        ))}
      </div>

      {pageItems.length > 0 ? (
        <div className="space-y-3">
          {pageItems.map((task) => (
            <TaskCard key={task.id} task={task} />
          ))}
        </div>
      ) : (
        <div className="rounded-lg border bg-card p-12 text-center text-muted-foreground">
          <p>{search ? `没找到匹配 “${search}” 的任务` : '当前筛选下没有任务'}</p>
          {search && (
            <Button variant="outline" size="sm" className="mt-3" onClick={() => setSearch('')}>
              清空搜索
            </Button>
          )}
        </div>
      )}

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
