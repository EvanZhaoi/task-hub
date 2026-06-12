import { useMemo } from 'react'
import { Link } from 'react-router'
import { CheckCircle2, XCircle, Hourglass, Inbox, ChevronRight } from 'lucide-react'
import { useUserStore } from '@/stores/user'
import { useTasksStore } from '@/stores/tasks'
import { useBidsStore } from '@/stores/bids'
import { GanttChart } from '@/components/GanttChart'
import { StatusBadge } from '@/components/StatusBadge'
import { ComplexityBadge } from '@/components/ComplexityBadge'
import { UserAvatar } from '@/components/UserAvatar'
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
    <div className="mx-auto max-w-5xl px-6 py-8 page-enter">
      <Card className="mb-6">
        <CardContent className="pt-6">
          <div className="flex items-center gap-4">
            <UserAvatar user={currentUser} size="lg" />
            <div className="flex-1">
              <h1 className="text-xl font-semibold tracking-tight text-foreground">{currentUser.name}</h1>
              <p className="text-sm text-muted-foreground mt-0.5">
                {currentUser.department} · {roleLabel[currentUser.role]}
              </p>
            </div>
            <div className="flex gap-6 text-center text-sm">
              <Stat label="发布" value={myPublished.length} />
              <Stat label="投标" value={myBidded.length} />
              <Stat label="接单" value={myAssigned.length} />
            </div>
          </div>
        </CardContent>
      </Card>

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
                <TaskRow
                  key={t.id}
                  task={t}
                  sub={`预算 ${formatMoney(t.budget)} · 期望 ${formatDate(t.expectedDelivery)} · ${bids.filter(b => b.taskId === t.id).length} 人投标`}
                />
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
                const statusInfo = myBid?.status === 'ACCEPTED'
                  ? { icon: CheckCircle2, text: '已中标', cls: 'text-emerald-600' }
                  : myBid?.status === 'LOST'
                  ? { icon: XCircle, text: '未中', cls: 'text-gray-500' }
                  : { icon: Hourglass, text: '等待中', cls: 'text-blue-600' }
                const { icon: StatusIcon, text: statusText, cls: statusCls } = statusInfo
                return (
                  <TaskRow
                    key={t.id}
                    task={t}
                    sub={
                      <span className="inline-flex items-center gap-3">
                        <span>我的报价 {formatMoney(myBid?.amount)}</span>
                        <span>交期 {formatDate(myBid?.deliveryDate)}</span>
                        <span className={`inline-flex items-center gap-1 ${statusCls}`}>
                          <StatusIcon className="h-3.5 w-3.5" />
                          {statusText}
                        </span>
                      </span>
                    }
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
              <Card className="">
                <CardHeader className="pb-3">
                  <CardTitle className="text-base">我的任务时间线</CardTitle>
                </CardHeader>
                <CardContent>
                  <GanttChart tasks={myAssigned} mode="personal" />
                </CardContent>
              </Card>
            </>
          )}
        </TabsContent>
      </Tabs>
    </div>
  )
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div>
      <div className="text-2xl font-semibold text-foreground tabular-nums">{value}</div>
      <div className="text-xs text-muted-foreground mt-0.5">{label}</div>
    </div>
  )
}

function EmptyState({ text }: { text: string }) {
  return (
    <div className="py-16 text-center">
      <Inbox className="h-10 w-10 mx-auto text-muted-foreground/30 mb-3" />
      <p className="text-sm text-muted-foreground">{text}</p>
    </div>
  )
}

function TaskRow({ task, sub }: { task: any; sub: React.ReactNode }) {
  return (
    <Link
      to={`/task/${task.id}`}
      className="group block rounded-lg bg-card p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition-shadow hover:shadow-[0_4px_10px_rgba(15,23,42,0.06)]"
    >
      <div className="flex items-center gap-3">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <h3 className="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">
              {task.title}
            </h3>
            <ComplexityBadge complexity={task.complexity} />
            <StatusBadge status={task.status} />
          </div>
          <div className="text-xs text-muted-foreground mt-1.5">{sub}</div>
        </div>
        <ChevronRight className="h-4 w-4 text-muted-foreground/50 group-hover:text-muted-foreground group-hover:translate-x-0.5 transition-all" />
      </div>
    </Link>
  )
}