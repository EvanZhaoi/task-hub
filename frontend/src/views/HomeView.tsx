import { Card, CardContent } from '@/components/ui/card'

export function HomeView() {
  return (
    <div className="mx-auto max-w-7xl px-6 py-8">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-foreground">任务大厅</h1>
        <p className="mt-1 text-sm text-muted-foreground">所有公开任务，可投标</p>
      </div>
      <Card>
        <CardContent className="py-12 text-center text-muted-foreground">
          <p>任务列表</p>
          <p className="mt-2 text-xs">Phase 1 待实现：搜索 / 筛选 / 分页 / TaskCard 组件</p>
        </CardContent>
      </Card>
    </div>
  )
}
