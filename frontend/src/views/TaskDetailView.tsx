import { useParams, Link, useNavigate } from 'react-router'
import { useState } from 'react'
import { ArrowLeft, FileText, Download, History } from 'lucide-react'
import { useTasksStore } from '@/stores/tasks'
import { useBidsStore } from '@/stores/bids'
import { useAttachmentsStore } from '@/stores/attachments'
import { useChangeLogsStore } from '@/stores/changelogs'
import { useUserStore } from '@/stores/user'
import { StatusBadge } from '@/components/StatusBadge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { formatMoney, formatDate, fileSizeFmt, formatRelative, changeTypeLabel, bidStatusLabel } from '@/utils/format'
import { cn } from '@/lib/utils'

export function TaskDetailView() {
  const { id } = useParams<{ id: string }>()
  const task = useTasksStore((s) => (id ? s.getById(id) : undefined))
  const bids = useBidsStore((s) => (id ? s.getByTaskId(id) : []))
  const attachments = useAttachmentsStore((s) => (id ? s.getByTaskId(id) : []))
  const changeLogs = useChangeLogsStore((s) => (id ? s.getByTaskId(id) : []))

  const currentUser = useUserStore((s) => s.currentUser)
  const acceptBid = useBidsStore((s) => s.markOthersLost)
  const setStatus = useTasksStore((s) => s.setStatus)
  const updateTask = useTasksStore((s) => s.updateTask)
  const addBid = useBidsStore((s) => s.addBid)
  const addLog = useChangeLogsStore((s) => s.addLog)
  const navigate = useNavigate()

  const [bidOpen, setBidOpen] = useState(false)
  const [extOpen, setExtOpen] = useState(false)
  const [bidAmount, setBidAmount] = useState('')
  const [bidDelivery, setBidDelivery] = useState('')
  const [bidProposal, setBidProposal] = useState('')
  const [extDelivery, setExtDelivery] = useState('')
  const [extReason, setExtReason] = useState('')

  if (!task) {
    return (
      <div className="mx-auto max-w-5xl px-6 py-8 text-center text-muted-foreground">
        <p>任务不存在</p>
        <Link to="/" className="text-primary hover:underline mt-2 inline-block">返回任务大厅</Link>
      </div>
    )
  }

  const isPublisher = currentUser?.id === task.createdBy
  const isAssignee = task.assignedBidId
    ? bids.find((b) => b.id === task.assignedBidId)?.bidderId === currentUser?.id
    : false
  const alreadyBidded = currentUser
    ? bids.some((b) => b.taskId === task.id && b.bidderId === currentUser.id)
    : false
  const canBid = task.status === 'OPEN' && currentUser?.role === 'developer' && !alreadyBidded
  const canSelectBid = task.status === 'OPEN' && isPublisher
  const canComplete = task.status === 'ASSIGNED' && isPublisher
  const canRequestExtension = task.status === 'ASSIGNED' && isAssignee
  const canCancel = (task.status === 'DRAFT' || task.status === 'OPEN') && isPublisher
  const canClone = task.status === 'FAILED' && isPublisher

  const handleSubmitBid = () => {
    if (!currentUser || !bidAmount || !bidDelivery) {
      alert('请填写金额和交期')
      return
    }
    addBid({
      id: 'b_' + Date.now(),
      taskId: task.id,
      bidderId: currentUser.id,
      amount: parseInt(bidAmount),
      deliveryDate: bidDelivery,
      proposal: bidProposal,
      status: 'ACTIVE',
      createdAt: new Date().toISOString().slice(0, 10),
    })
    setBidOpen(false)
    setBidAmount('')
    setBidDelivery('')
    setBidProposal('')
  }

  const handleSelectBid = (bidId: string) => {
    const bid = bids.find((b) => b.id === bidId)
    if (!bid) return
    acceptBid(task.id, bidId)
    updateTask(task.id, {
      assignedBidId: bidId,
      finalAmount: bid.amount,
      finalDelivery: bid.deliveryDate,
      status: 'ASSIGNED',
    })
  }

  const handleComplete = () => {
    if (confirm('确认任务完成？')) setStatus(task.id, 'COMPLETED')
  }

  const handleCancel = () => {
    if (confirm('确认取消任务？')) setStatus(task.id, 'CANCELLED')
  }

  const handleRequestExtension = () => {
    if (!currentUser || !extDelivery) {
      alert('请填写新交期')
      return
    }
    updateTask(task.id, { finalDelivery: extDelivery })
    addLog({
      id: 'cl_' + Date.now(),
      taskId: task.id,
      changeType: 'EXTENSION',
      oldValue: { finalDelivery: task.finalDelivery },
      newValue: { finalDelivery: extDelivery },
      reason: extReason,
      agreedBy: [currentUser.id, task.createdBy],
      createdBy: currentUser.id,
      createdAt: new Date().toISOString().slice(0, 16).replace('T', ' '),
    })
    setExtOpen(false)
    setExtDelivery('')
    setExtReason('')
  }

  const handleClone = () => {
    updateTask(task.id, {
      ...task,
      id: 't_' + Date.now(),
      status: 'DRAFT',
      finalAmount: undefined,
      finalDelivery: undefined,
      assignedBidId: undefined,
    })
    navigate('/')
  }

  return (
    <div className="mx-auto max-w-5xl px-6 py-8">
      <Link to="/" className="text-sm text-muted-foreground hover:text-foreground inline-flex items-center gap-1 mb-4">
        <ArrowLeft className="h-3 w-3" /> 返回任务大厅
      </Link>

      {/* 标题 + 状态 */}
      <div className="rounded-lg border bg-card p-6 mb-4">
        <div className="flex items-start justify-between gap-4">
          <h1 className="text-3xl font-bold text-foreground leading-tight flex-1">{task.title}</h1>
          <StatusBadge status={task.status} />
        </div>
        <div className="flex items-center gap-5 text-sm text-muted-foreground mt-4 flex-wrap">
          <span>👤 {userName(task.createdBy)}</span>
          <span>💰 预算 <span className="text-foreground font-medium">{formatMoney(task.budget)}</span></span>
          <span>📅 期望 <span className="text-foreground">{formatDate(task.expectedDelivery)}</span></span>
          <span>🕐 创建于 <span className="text-foreground">{formatDate(task.createdAt)}</span></span>
        </div>
      </div>

      {/* 需求描述（富文本渲染） */}
      <Card className="mb-4">
        <CardHeader className="pb-3">
          <div className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">需求描述</div>
        </CardHeader>
        <CardContent>
          <div
            className="prose-task"
            dangerouslySetInnerHTML={{ __html: task.description || '<p class="text-muted-foreground">（无）</p>' }}
          />
        </CardContent>
      </Card>

      {/* 补充信息 */}
      <Card className="mb-4">
        <CardContent>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <Field label="预算" value={formatMoney(task.budget)} />
            <Field label="最终金额" value={formatMoney(task.finalAmount)} />
            <Field label="最终交期" value={formatDate(task.finalDelivery)} />
            <Field label="招标截止" value={formatDate(task.biddingDeadline)} />
          </div>
        </CardContent>
      </Card>

      {/* 附件 */}
      {attachments.length > 0 && (
        <Card className="mb-4">
          <CardHeader className="pb-3">
            <CardTitle>附件 ({attachments.length})</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {attachments.map((att) => (
                <div key={att.id} className="flex items-center gap-3 p-2 hover:bg-accent/50 rounded">
                  <div className="flex h-9 w-9 items-center justify-center rounded bg-primary/10 text-primary text-xs font-semibold">
                    <FileText className="h-4 w-4" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-medium truncate">{att.fileName}</div>
                    <div className="text-xs text-muted-foreground">
                      {fileSizeFmt(att.fileSize)} · {userName(att.uploadedBy)} 上传于 {formatDate(att.uploadedAt)}
                    </div>
                  </div>
                  <Button variant="ghost" size="sm">
                    <Download className="h-3 w-3" />
                  </Button>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* 投标列表 */}
      {(bids.length > 0 || task.status === 'OPEN') && (
        <Card className="mb-4">
          <CardHeader className="flex flex-row items-center justify-between pb-3">
            <CardTitle>投标 ({bids.length})</CardTitle>
            {task.status === 'OPEN' && (
              <span className="text-xs text-muted-foreground">💡 投标记录互相不可见（防串标）</span>
            )}
          </CardHeader>
          <CardContent>
            {bids.length === 0 ? (
              <p className="text-sm text-muted-foreground text-center py-3">还没有人投标</p>
            ) : (
              <div className="space-y-2">
                {bids.map((bid) => (
                  <div
                    key={bid.id}
                    className={cn(
                      'flex items-start gap-3 p-3 rounded border',
                      bid.status === 'ACCEPTED' ? 'border-green-200 bg-green-50'
                        : bid.status === 'LOST' ? 'border-gray-200 bg-gray-50 opacity-60'
                        : 'border-border',
                    )}
                  >
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary text-xs font-semibold">
                      {userName(bid.bidderId).charAt(0)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">{userName(bid.bidderId)}</span>
                        <span className={cn('text-xs px-2 py-0.5 rounded-full', bidStatusClass(bid.status))}>
                          {bidStatusLabel[bid.status]}
                        </span>
                        {bid.proposal && <span className="text-xs text-muted-foreground">· {formatRelative(bid.createdAt)}</span>}
                      </div>
                      {bid.proposal && <p className="text-sm text-foreground mt-1">{bid.proposal}</p>}
                      <div className="text-xs text-muted-foreground mt-1.5 flex gap-4">
                        <span>报价 <span className="text-foreground font-medium">{formatMoney(bid.amount)}</span></span>
                        <span>交期 <span className="text-foreground">{formatDate(bid.deliveryDate)}</span></span>
                      </div>
                    </div>
                    {canSelectBid && bid.status === 'ACTIVE' && (
                      <Button size="sm" onClick={() => handleSelectBid(bid.id)}>选 TA</Button>
                    )}
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* 变更记录 */}
      {changeLogs.length > 0 && (
        <Card className="mb-4">
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2">
              <History className="h-4 w-4" /> 变更记录 ({changeLogs.length})
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {changeLogs.map((log) => (
                <div key={log.id} className="border-l-2 border-primary/30 pl-3 py-1.5">
                  <div className="flex items-center gap-2 text-sm">
                    <span className="font-medium">{changeTypeLabel[log.changeType]}</span>
                    <span className="text-xs text-muted-foreground">{log.createdAt}</span>
                  </div>
                  {log.oldValue && log.newValue && (
                    <div className="text-xs text-muted-foreground mt-1">
                      {describeChange(log)}
                    </div>
                  )}
                  {log.reason && (
                    <div className="text-sm text-foreground mt-1">原因：{log.reason}</div>
                  )}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* 操作按钮 */}
      <Card className="mb-4">
        <CardContent className="flex flex-wrap items-center gap-2">
          {canBid && (
            <Dialog open={bidOpen} onOpenChange={setBidOpen}>
              <DialogTrigger asChild>
                <Button>💰 投标</Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>投标</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                  <div>
                    <label className="text-sm text-foreground">投标金额（¥）</label>
                    <Input type="number" value={bidAmount} onChange={(e) => setBidAmount(e.target.value)} placeholder="例如 800" className="mt-1" />
                  </div>
                  <div>
                    <label className="text-sm text-foreground">预计交期</label>
                    <Input type="date" value={bidDelivery} onChange={(e) => setBidDelivery(e.target.value)} className="mt-1" />
                  </div>
                  <div>
                    <label className="text-sm text-foreground">方案描述（可选）</label>
                    <Textarea value={bidProposal} onChange={(e) => setBidProposal(e.target.value)} className="mt-1" />
                  </div>
                </div>
                <div className="flex gap-2 justify-end mt-4">
                  <Button variant="outline" onClick={() => setBidOpen(false)}>取消</Button>
                  <Button onClick={handleSubmitBid}>提交投标</Button>
                </div>
              </DialogContent>
            </Dialog>
          )}

          {canComplete && (
            <Button onClick={handleComplete}>✓ 确认完成</Button>
          )}

          {canRequestExtension && (
            <Dialog open={extOpen} onOpenChange={setExtOpen}>
              <DialogTrigger asChild>
                <Button variant="outline">📅 申请延期</Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>申请延期</DialogTitle>
                </DialogHeader>
                <p className="text-xs text-muted-foreground mb-3">系统会记录新交期、原因、双方同意。</p>
                <div className="space-y-3">
                  <div>
                    <label className="text-sm text-foreground">新交期</label>
                    <Input type="date" value={extDelivery} onChange={(e) => setExtDelivery(e.target.value)} className="mt-1" />
                  </div>
                  <div>
                    <label className="text-sm text-foreground">原因</label>
                    <Textarea value={extReason} onChange={(e) => setExtReason(e.target.value)} className="mt-1" placeholder="例如：被老板加塞做新任务" />
                  </div>
                </div>
                <div className="flex gap-2 justify-end mt-4">
                  <Button variant="outline" onClick={() => setExtOpen(false)}>取消</Button>
                  <Button onClick={handleRequestExtension}>提交延期</Button>
                </div>
              </DialogContent>
            </Dialog>
          )}

          {canCancel && (
            <Button variant="destructive" onClick={handleCancel}>✕ 取消任务</Button>
          )}

          {canClone && (
            <Button variant="outline" onClick={handleClone}>📋 复制新建</Button>
          )}

          {!canBid && !canComplete && !canRequestExtension && !canCancel && !canClone && (
            <span className="text-xs text-muted-foreground">你的当前身份对此任务无操作权限</span>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function Field({ label, value }: { label: string; value?: string }) {
  return (
    <div>
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className="text-base font-medium mt-0.5">{value || '—'}</div>
    </div>
  )
}

function userName(id: string): string {
  const map: Record<string, string> = { u1: '李雷', u2: '韩梅梅', u3: '王运维', u4: '张总', u5: '陈PM', u6: 'Evan' }
  return map[id] || '未知'
}

function bidStatusClass(status: string): string {
  return {
    ACTIVE: 'bg-blue-100 text-blue-700',
    ACCEPTED: 'bg-green-100 text-green-700',
    LOST: 'bg-gray-100 text-gray-500',
    WITHDRAWN: 'bg-gray-100 text-gray-400',
  }[status] || ''
}

function describeChange(log: { changeType: string; oldValue?: any; newValue?: any }): string {
  if (log.changeType === 'EXTENSION') return `${log.oldValue?.finalDelivery} → ${log.newValue?.finalDelivery}`
  if (log.changeType === 'AMOUNT') return `¥${log.oldValue?.finalAmount} → ¥${log.newValue?.finalAmount}`
  if (log.changeType === 'DELIVERY') return `${log.oldValue?.finalDelivery} → ${log.newValue?.finalDelivery}`
  return '—'
}
