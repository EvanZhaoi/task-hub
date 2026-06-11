import { useParams, Link } from 'react-router'
import { Card, CardContent } from '@/components/ui/card'

export function TaskDetailView() {
  const { id } = useParams<{ id: string }>()

  return (
    <div className="mx-auto max-w-5xl px-6 py-8">
      <Link to="/" className="text-sm text-muted-foreground hover:text-foreground">
        ← 返回任务大厅
      </Link>
      <h1 className="mt-4 text-3xl font-bold text-foreground">任务详情</h1>
      <p className="mt-2 text-sm text-muted-foreground">任务 ID：{id}</p>
      <Card className="mt-6">
        <CardContent className="py-12 text-center text-muted-foreground">
          <p>任务详情</p>
          <p className="mt-2 text-xs">Phase 1 待实现：描述渲染 / 投标列表 / 选标 / 完成 / 延期 / 附件</p>
        </CardContent>
      </Card>
    </div>
  )
}
