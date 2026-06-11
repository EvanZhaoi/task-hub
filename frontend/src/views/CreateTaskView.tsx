import { Card, CardContent } from '@/components/ui/card'

export function CreateTaskView() {
  return (
    <div className="mx-auto max-w-3xl px-6 py-8">
      <h1 className="text-2xl font-semibold text-foreground">发布任务</h1>
      <p className="mt-1 text-sm text-muted-foreground">两种模式：招标 或 直接指名</p>
      <Card className="mt-6">
        <CardContent className="py-12 text-center text-muted-foreground">
          <p>任务创建表单</p>
          <p className="mt-2 text-xs">Phase 1 待实现：招标表单 + 直接指名表单 + TipTap 富文本 + 文件上传</p>
        </CardContent>
      </Card>
    </div>
  )
}
