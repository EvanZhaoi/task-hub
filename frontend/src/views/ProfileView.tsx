import { useMemo } from 'react'
import { Link } from 'react-router'
import { useUserStore } from '@/stores/user'
import { useTasksStore } from '@/stores/tasks'
import { useBidsStore } from '@/stores/bids'
import { GanttChart } from '@/components/GanttChart'
import { StatusBadge } from '@/components/StatusBadge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { formatMoney, formatDate, roleLabel } from '@/utils/format'

export function ProfileView() {
  const currentUser = useUserStore((s) => s.currentUser)
  const tasks = useTasksStore((s) => s.list)
  const bids = useBidsStore((s) => s.bids)

  const myPublished = useMemo(
    () => tasks.filter((t) => t.createdBy === currentUser?.id),
    [tasks, currentUser?.id],
  )
  const myBidded = useMemo(
    () =>
      Array.from(new Set(bids.filter((b) => b.bidderId === currentUser?.id).map((b) => b.taskId)))
        .map((id) => tasks.find((t) => t.id === id))
        .filter(Boolean),
    [bids, currentUser?.id, tasks],
  )
  const myAssigned = useMemo(
    () =>
      tasks.filter((t) => {
        if (!t.assignedBidId) return false
        const bid = bids.find((b) => b.id === t.assignedBidId)
        return bid?.bidderId === currentUser?.id
      }),
    [tasks, bids, currentUser?.id],
  )

  if (!currentUser) return null

  return (
    <div className="mx-auto max-w-5xl px-6 py-8">
      <div className="flex items-center gap-4 mb-6">
        <div
          className="flex h-10 w-10 items-center justify-center rounded-full text-sm font-medium text-white"
          style={{
            background: currentUser.role === 'publisher' ? 'linear-gradient(135deg, #f97316 0%, #fb923c 100%)'
              : currentUser.role === 'boss' ? 'linear-gradient(135deg, #1a1a1a 0%, #4b5563 100%)'
              : 'linear-gradient(135deg, #5e6ad2 0%, #8b5cf6 100%)',
          }}
        >
          {currentUser.name.charAt(0)}
        </div>
        <div>
          <h1 className="text-2xl font-semibold text-foreground">{currentUser.name}</h1>
          <p className="text-sm text-muted-foreground mt-1">{currentUser.department} · {roleLabel[currentUser.role]}</p>
        </div>
      </div>

      <Tabs defaultValue="published">
        <TabsList>
          <TabsTrigger value="published">我发布的 ({myPublished.length})</TabsTrigger>
          <TabsTrigger value="bidded">我投标的 ({myBidded.length})</TabsTrigger>
          <TabsTrigger value="assigned">我接的 ({myAssigned.length})</TabsTrigger>
        </TabsList>

        <TabsContent value="published">
          {myPublished.length === 0 ? (
            <EmptyState text="还没有发布过任务" />
          ) : (
            <div className="space-y-2">
              {myPublished.map((t) => (
                <TaskRow key={t.id} task={t} sub={`预算 ${formatMoney(t.budget)} · 期望 ${formatDate(t.expectedDelivery)} · ${bids.filter(b => b.taskId === t.id).length} 人投标`} />
              ))}
            </div>
          )}
        </TabsContent>

        <TabsContent value="bidded">
          {myBidded.length === 0 ? (
            <EmptyState text="还没有投标过任务" />
          ) : (
            <div className="space-y-2">
              {myBidded.map((t) => {
                if (!t) return null
                const myBid = bids.find((b) => b.taskId === t.id && b.bidderId === currentUser.id)
                const statusText = myBid?.status === 'ACCEPTED' ? '✅ 已中标' : myBid?.status === 'LOST' ? '❌ 未中' : '⏳ 等待中'
                return (
                  <TaskRow
                    key={t.id}
                    task={t}
                    sub={`我的报价 ${formatMoney(myBid?.amount)} · 交期 ${formatDate(myBid?.deliveryDate)} · ${statusText}`}
                  />
                )
              })}
            </div>
          )}
        </TabsContent>

        <TabsContent value="assigned">
          {myAssigned.length === 0 ? (
            <EmptyState text="还没有接单任务" />
          ) : (
            <>
              <div className="space-y-2 mb-4">
                {myAssigned.map((t) => (
                  <TaskRow
                    key={t.id}
                    task={t}
                    sub={`金额 ${formatMoney(t.finalAmount)} · 交期 ${formatDate(t.finalDelivery)}`}
                  />
                ))}
              </div>
              {myAssigned.length > 0 && (
                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle>我的任务时间线</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <GanttChart tasks={myAssigned} mode="personal" />
                  </CardContent>
                </Card>
              )}
            </>
          )}
        </TabsContent>
      </Tabs>
    </div>
  )
}

function EmptyState({ text }: { text: string }) {
  return (
    <div className="rounded-lg border bg-card p-12 text-center text-muted-foreground text-sm">
      {text}
    </div>
  )
}

function TaskRow({ task, sub }: { task: any; sub: string }) {
  return (
    <Link
      to={`/task/${task.id}`}
      className="block rounded-lg border bg-card p-4 transition-colors hover:border-foreground/20"
    >
      <div className="flex items-center justify-between gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <h3 className="text-sm font-medium truncate">{task.title}</h3>
            <StatusBadge status={task.status} />
          </div>
          <div className="text-xs text-muted-foreground mt-1">{sub}</div>
        </div>
      </div>
    </Link>
  )
}
