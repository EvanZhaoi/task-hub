import { useState, useMemo } from 'react'
import { useUserStore } from '@/stores/user'
import { useTasksStore } from '@/stores/tasks'
import { MOCK_PAYMENT_ACCOUNTS } from '@/mocks'
import { GanttChart } from '@/components/GanttChart'
import { Card, CardContent } from '@/components/ui/card'

export function BossGanttView() {
  const isBoss = useUserStore((s) => s.isBoss())
  const tasks = useTasksStore((s) => s.list)

  const [accountFilter, setAccountFilter] = useState<string>('all')

  const filtered = useMemo(() => {
    let arr = tasks.filter((t) => t.status !== 'DRAFT')
    if (accountFilter !== 'all') {
      arr = arr.filter((t) => t.paymentAccountId === accountFilter)
    }
    return arr
  }, [tasks, accountFilter])

  if (!isBoss) {
    return (
      <div className="mx-auto max-w-7xl px-6 py-8 text-center text-muted-foreground">
        <p>老板视图仅限 boss 角色访问</p>
        <p className="text-xs mt-2">请在右上角切换到 Evan（老板）身份</p>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-7xl px-6 py-8">
      <div className="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-foreground">老板视图</h1>
          <p className="text-sm text-muted-foreground mt-1">所有任务 · 甘特图 · 按状态着色</p>
        </div>
        <div className="flex items-center gap-4">
          <select
            value={accountFilter}
            onChange={(e) => setAccountFilter(e.target.value)}
            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
          >
            <option value="all">全部付款账号</option>
            {MOCK_PAYMENT_ACCOUNTS.map((pa) => (
              <option key={pa.id} value={pa.id}>{pa.name}</option>
            ))}
          </select>
          <div className="flex gap-3 text-xs">
            <span className="flex items-center gap-1.5">
              <span className="inline-block w-3 h-3 rounded bg-blue-500" /> 招标中
            </span>
            <span className="flex items-center gap-1.5">
              <span className="inline-block w-3 h-3 rounded bg-orange-500" /> 进行中
            </span>
            <span className="flex items-center gap-1.5">
              <span className="inline-block w-3 h-3 rounded bg-emerald-500" /> 已完成
            </span>
            <span className="flex items-center gap-1.5">
              <span className="inline-block w-3 h-3 rounded bg-gray-500" /> 流标
            </span>
          </div>
        </div>
      </div>

      <Card>
        <CardContent>
          <GanttChart tasks={filtered} mode="boss" />
        </CardContent>
      </Card>

      <div className="mt-4 flex items-center justify-between">
        <span className="text-sm text-muted-foreground">共 {filtered.length} 个任务</span>
        <span className="text-xs text-muted-foreground">💡 点击任务条可跳转到详情</span>
      </div>
    </div>
  )
}
