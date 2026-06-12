import { useState, useMemo } from 'react'
import { Info, Lock } from 'lucide-react'
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
      <div className="mx-auto max-w-7xl px-6 py-16 text-center page-enter">
        <div className="inline-block py-12">
          <Lock className="h-8 w-8 mx-auto text-muted-foreground/50 mb-3" />
          <p className="text-sm text-muted-foreground">老板视图仅限 boss 角色访问</p>
          <p className="text-xs text-muted-foreground mt-2">请在右上角切换到 Evan（老板）身份</p>
        </div>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-7xl px-6 py-8 page-enter">
      <div className="mb-6 flex items-end justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-[26px] font-semibold tracking-tight text-foreground">老板视图</h1>
          <p className="text-sm text-muted-foreground mt-1">所有任务 · 甘特图 · 按状态着色</p>
        </div>
        <div className="flex items-center gap-4 flex-wrap">
          <select
            value={accountFilter}
            onChange={(e) => setAccountFilter(e.target.value)}
            className="h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40 focus:border-primary/50 transition-colors"
          >
            <option value="all">全部付款账号</option>
            {MOCK_PAYMENT_ACCOUNTS.map((pa) => (
              <option key={pa.id} value={pa.id}>{pa.name}</option>
            ))}
          </select>
          <div className="flex gap-4 text-xs text-muted-foreground">
            <Legend dotClass="bg-blue-500" label="招标中" />
            <Legend dotClass="bg-orange-500" label="进行中" />
            <Legend dotClass="bg-emerald-500" label="已完成" />
            <Legend dotClass="bg-gray-500" label="流标" />
          </div>
        </div>
      </div>

      <Card className="border-border/60">
        <CardContent>
          <GanttChart tasks={filtered} mode="boss" />
        </CardContent>
      </Card>

      <div className="mt-4 flex items-center justify-between text-sm">
        <span className="text-muted-foreground">共 <span className="text-foreground font-medium tabular-nums">{filtered.length}</span> 个任务</span>
        <span className="text-xs text-muted-foreground inline-flex items-center gap-1">
          <Info className="h-3 w-3" />
          点击任务条可跳转到详情
        </span>
      </div>
    </div>
  )
}

function Legend({ dotClass, label }: { dotClass: string; label: string }) {
  return (
    <span className="inline-flex items-center gap-1.5">
      <span className={`inline-block w-2.5 h-2.5 rounded-sm ${dotClass}`} />
      {label}
    </span>
  )
}