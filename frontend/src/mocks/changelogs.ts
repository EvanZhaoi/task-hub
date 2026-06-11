import type { ChangeLog } from '@/api/types'

export const MOCK_CHANGE_LOGS: ChangeLog[] = [
  {
    id: 'cl1',
    taskId: 't7',
    changeType: 'EXTENSION',
    oldValue: { finalDelivery: '2026-06-12' },
    newValue: { finalDelivery: '2026-06-14' },
    reason: '老板加塞，原任务延期 2 天。已与发布方协商一致',
    agreedBy: ['u6', 'u2'],
    createdBy: 'u2',
    createdAt: '2026-06-11 10:30',
  },
  {
    id: 'cl2',
    taskId: 't3',
    changeType: 'AMOUNT',
    oldValue: { finalAmount: 2400 },
    newValue: { finalAmount: 2800 },
    reason: '中标记账',
    agreedBy: ['u4', 'u1'],
    createdBy: 'u1',
    createdAt: '2026-06-07 14:20',
  },
  {
    id: 'cl3',
    taskId: 't2',
    changeType: 'DELIVERY',
    oldValue: { finalDelivery: '2026-06-20' },
    newValue: { finalDelivery: '2026-06-22' },
    reason: '需求略调整，多 2 天',
    agreedBy: ['u5', 'u2'],
    createdBy: 'u5',
    createdAt: '2026-06-09 09:00',
  },
]
