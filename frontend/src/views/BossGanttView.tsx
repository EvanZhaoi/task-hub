import { Card, CardContent } from '@/components/ui/card'

export function BossGanttView() {
  return (
    <div className="mx-auto max-w-7xl px-6 py-8">
      <h1 className="text-2xl font-semibold text-foreground">老板视图</h1>
      <p className="mt-1 text-sm text-muted-foreground">所有任务 · 甘特图 · 按状态着色</p>
      <Card className="mt-6">
        <CardContent className="py-12 text-center text-muted-foreground">
          <p>老板甘特图</p>
          <p className="mt-2 text-xs">Phase 1 待实现：通用 GanttChart 组件 + 搜索 + 付款账号筛选</p>
        </CardContent>
      </Card>
    </div>
  )
}
