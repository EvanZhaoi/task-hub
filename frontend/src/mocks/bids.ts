import type { Bid } from '@/api/types'

export const MOCK_BIDS: Bid[] = [
  { id: 'b1',  taskId: 't1', bidderId: 'u1', amount: 750,  deliveryDate: '2026-06-24', proposal: 'Vue 3 + Tailwind 重写，3 天能搞定',                status: 'ACTIVE',  createdAt: '2026-06-10' },
  { id: 'b1b', taskId: 't1', bidderId: 'u2', amount: 800,  deliveryDate: '2026-06-25', proposal: '我俩可以合作，前端我做',                              status: 'ACTIVE',  createdAt: '2026-06-10' },
  { id: 'b2',  taskId: 't2', bidderId: 'u2', amount: 450,  deliveryDate: '2026-06-22', proposal: '后端用 EasyExcel，前端加个按钮',                    status: 'ACCEPTED', createdAt: '2026-06-09' },
  { id: 'b3',  taskId: 't3', bidderId: 'u1', amount: 2800, deliveryDate: '2026-07-15', proposal: '屎山代码我熟，加 40% 价。增量同步我做过类似的',   status: 'ACCEPTED', createdAt: '2026-06-06' },
  { id: 'b4',  taskId: 't4', bidderId: 'u1', amount: 1400, deliveryDate: '2026-06-29', proposal: 'A/B 测试方案我可以做',                              status: 'ACTIVE',  createdAt: '2026-06-11' },
  { id: 'b4b', taskId: 't4', bidderId: 'u2', amount: 1500, deliveryDate: '2026-06-28', proposal: '用 Google Optimize 做',                              status: 'ACTIVE',  createdAt: '2026-06-11' },
  { id: 'b4c', taskId: 't4', bidderId: 'u3', amount: 1450, deliveryDate: '2026-06-29', proposal: '顺便把监控埋点也做了',                                status: 'ACTIVE',  createdAt: '2026-06-11' },
  { id: 'b5',  taskId: 't5', bidderId: 'u1', amount: 2800, deliveryDate: '2026-05-29', proposal: '',                                                  status: 'ACCEPTED', createdAt: '2026-05-22' },
  { id: 'b7',  taskId: 't7', bidderId: 'u2', amount: 1200, deliveryDate: '2026-06-14', proposal: '紧急任务优先做',                                     status: 'ACCEPTED', createdAt: '2026-06-11' },
  { id: 'b8a', taskId: 't8', bidderId: 'u1', amount: 700,  deliveryDate: '2026-06-27', proposal: '加索引 + 重写 SQL，预计 3 天',                        status: 'ACTIVE',  createdAt: '2026-06-11' },
  { id: 'b8b', taskId: 't8', bidderId: 'u3', amount: 900,  deliveryDate: '2026-06-26', proposal: '我用 EXPLAIN 看一下，顺便优化表结构',                status: 'ACTIVE',  createdAt: '2026-06-11' },
]
