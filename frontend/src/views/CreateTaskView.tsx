import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Briefcase, Target } from 'lucide-react'
import { useUserStore } from '@/stores/user'
import { useTasksStore } from '@/stores/tasks'
import { MOCK_PAYMENT_ACCOUNTS, MOCK_USERS } from '@/mocks'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import type { Task, User, Difficulty } from '@/api/types'
import { difficultyLabel } from '@/utils/format'

export function CreateTaskView() {
  const navigate = useNavigate()
  const currentUser = useUserStore((s) => s.currentUser)
  const addTask = useTasksStore((s) => s.addTask)

  const [open, setOpen] = useState<'regular' | 'direct' | null>(null)

  if (currentUser?.role === 'developer') {
    return (
      <div className="mx-auto max-w-3xl px-6 py-8 text-center text-muted-foreground">
        <p>只有发布者（publisher）和老板（boss）可以发布任务</p>
        <p className="text-xs mt-2">请在右上角切换到发布者身份</p>
      </div>
    )
  }

  const openCreate = (mode: 'regular' | 'direct') => setOpen(mode)
  const close = () => setOpen(null)

  return (
    <div className="mx-auto max-w-3xl px-6 py-8">
      <h1 className="text-2xl font-semibold text-foreground">发布任务</h1>
      <p className="text-sm text-muted-foreground mt-1">两种模式：招标 或 直接指名</p>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
        <Card className="cursor-pointer transition-colors hover:border-primary" onClick={() => openCreate('regular')}>
          <CardContent className="pt-6">
            <div className="flex items-center gap-3 mb-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-md bg-primary text-white">
                <Briefcase className="h-5 w-5" />
              </div>
              <div>
                <h3 className="text-base font-semibold">招标模式</h3>
                <p className="text-xs text-muted-foreground">开发者投标，你来选</p>
              </div>
            </div>
            <ul className="text-sm text-foreground space-y-1.5 list-disc pl-5">
              <li>填写任务信息，所有人可见</li>
              <li>开发者投标（金额、交期、方案）</li>
              <li>你从中选一个，任务进入进行中</li>
            </ul>
            <Button className="mt-4 w-full">发布招标任务</Button>
          </CardContent>
        </Card>

        <Card className="cursor-pointer transition-colors hover:border-primary" onClick={() => openCreate('direct')}>
          <CardContent className="pt-6">
            <div className="flex items-center gap-3 mb-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-md bg-orange-500 text-white">
                <Target className="h-5 w-5" />
              </div>
              <div>
                <h3 className="text-base font-semibold">直接指名</h3>
                <p className="text-xs text-muted-foreground">指定某个开发者，跳过招标</p>
              </div>
            </div>
            <ul className="text-sm text-foreground space-y-1.5 list-disc pl-5">
              <li>你已经知道找谁做</li>
              <li>填好金额和需求</li>
              <li>直接进入进行中（不招标）</li>
            </ul>
            <Button className="mt-4 w-full bg-orange-500 hover:bg-orange-600">直接指名</Button>
          </CardContent>
        </Card>
      </div>

      <CreateTaskDialog
        open={open === 'regular'}
        mode="regular"
        onClose={close}
        onSubmit={(t) => {
          addTask(t)
          close()
          navigate(`/task/${t.id}`)
        }}
      />
      <CreateTaskDialog
        open={open === 'direct'}
        mode="direct"
        onClose={close}
        onSubmit={(t) => {
          addTask(t)
          close()
          navigate(`/task/${t.id}`)
        }}
      />
    </div>
  )
}

interface CreateTaskDialogProps {
  open: boolean
  mode: 'regular' | 'direct'
  onClose: () => void
  onSubmit: (task: Task) => void
}

function CreateTaskDialog({ open, mode, onClose, onSubmit }: CreateTaskDialogProps) {
  const currentUser = useUserStore((s) => s.currentUser)!
  const developers = MOCK_USERS.filter((u) => u.role === 'developer')

  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [accountId, setAccountId] = useState(MOCK_PAYMENT_ACCOUNTS[0].id)
  const [budget, setBudget] = useState('')
  const [delivery, setDelivery] = useState('')
  const [difficulty, setDifficulty] = useState<Difficulty>('NORMAL')
  const [assigneeId, setAssigneeId] = useState<User['id']>(developers[0]?.id ?? '')

  const reset = () => {
    setTitle('')
    setDescription('')
    setBudget('')
    setDelivery('')
    setDifficulty('NORMAL')
  }

  const submit = () => {
    if (!title || !budget || !delivery) {
      alert('请填写标题、预算、交期')
      return
    }
    const today = new Date().toISOString().slice(0, 10)
    const newTask: Task = {
      id: 't_' + Date.now(),
      title,
      description: description || '<p class="text-muted-foreground">（无详细描述）</p>',
      paymentAccountId: accountId,
      budget: parseInt(budget),
      finalAmount: mode === 'direct' ? parseInt(budget) : undefined,
      expectedDelivery: delivery,
      finalDelivery: mode === 'direct' ? delivery : undefined,
      biddingDeadline: mode === 'direct' ? today : delivery,
      status: mode === 'direct' ? 'ASSIGNED' : 'OPEN',
      isDirect: mode === 'direct',
      difficulty,
      createdBy: currentUser.id,
      assignedBidId: undefined,
      createdAt: today,
      updatedAt: today,
    }
    onSubmit(newTask)
    reset()
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{mode === 'regular' ? '发布招标任务' : '直接指名'}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3 max-h-[60vh] overflow-y-auto">
          <Field label="任务标题 *">
            <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="简洁描述要做什么" />
          </Field>
          <Field label="详细描述">
            <Textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={4}
              placeholder="需求、验收标准、注意事项等（支持 HTML）"
            />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="付款账号 *">
              <select
                value={accountId}
                onChange={(e) => setAccountId(e.target.value)}
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              >
                {MOCK_PAYMENT_ACCOUNTS.map((pa) => (
                  <option key={pa.id} value={pa.id}>{pa.name}</option>
                ))}
              </select>
              <p className="text-xs text-muted-foreground mt-1">⚠️ 需线下与账号管理员确认</p>
            </Field>
            <Field label="预算金额（¥）*">
              <Input type="number" value={budget} onChange={(e) => setBudget(e.target.value)} placeholder="例如 800" />
            </Field>
          </div>
          <Field label="任务难度 *">
            <div className="grid grid-cols-3 gap-2">
              {(['EASY', 'NORMAL', 'HARD'] as Difficulty[]).map((d) => (
                <button
                  key={d}
                  type="button"
                  onClick={() => setDifficulty(d)}
                  className={`h-10 rounded-md border text-sm font-medium transition-colors ${
                    difficulty === d
                      ? d === 'EASY' ? 'border-green-500 bg-green-50 text-green-800'
                        : d === 'HARD' ? 'border-red-500 bg-red-50 text-red-800'
                        : 'border-primary bg-primary/10 text-primary'
                      : 'border-input bg-background hover:bg-accent'
                  }`}
                >
                  {difficultyLabel[d]}
                </button>
              ))}
            </div>
            <p className="text-xs text-muted-foreground mt-1">帮助开发者评估工作量（MVP 不影响定价）</p>
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="期望交期 *">
              <Input type="date" value={delivery} onChange={(e) => setDelivery(e.target.value)} />
            </Field>
            {mode === 'direct' && (
              <Field label="被指名人 *">
                <select
                  value={assigneeId}
                  onChange={(e) => setAssigneeId(e.target.value)}
                  className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  {developers.map((u) => (
                    <option key={u.id} value={u.id}>{u.name} · {u.department}</option>
                  ))}
                </select>
              </Field>
            )}
          </div>
          <Field label="附件（原型不实现）">
            <div className="flex h-16 items-center justify-center rounded-md border border-dashed border-input bg-muted/30 text-sm text-muted-foreground">
              📎 点击上传（原型不实现实际存储）
            </div>
          </Field>
        </div>
        <div className="flex gap-2 justify-end mt-4">
          <Button variant="outline" onClick={() => { reset(); onClose() }}>取消</Button>
          <Button onClick={submit}>{mode === 'regular' ? '发布' : '直接发布'}</Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="text-sm text-foreground">{label}</label>
      <div className="mt-1">{children}</div>
    </div>
  )
}
