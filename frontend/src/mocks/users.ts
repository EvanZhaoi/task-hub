import type { User } from '@/api/types'

export const MOCK_USERS: User[] = [
  { id: 'u1', name: '李雷',   role: 'developer', department: '产品研发部', email: 'lilei@mock.taskhub.local' },
  { id: 'u2', name: '韩梅梅', role: 'developer', department: '产品研发部', email: 'hanmm@mock.taskhub.local' },
  { id: 'u3', name: '王运维', role: 'developer', department: '运维部',     email: 'wangyw@mock.taskhub.local' },
  { id: 'u4', name: '张总',   role: 'publisher', department: '市场部',     email: 'zhangzong@mock.taskhub.local' },
  { id: 'u5', name: '陈PM',   role: 'publisher', department: '产品研发部', email: 'chenpm@mock.taskhub.local' },
  { id: 'u6', name: 'Evan',   role: 'boss',      department: 'CEO办公室',  email: 'evan@mock.taskhub.local' },
]

export const MOCK_PAYMENT_ACCOUNTS = [
  { id: 'pa1', name: '产品研发部·2026Q2 预算',   managerId: 'u5' },
  { id: 'pa2', name: '市场部·数字营销专项',       managerId: 'u4' },
  { id: 'pa3', name: '运维部·基础设施',           managerId: 'u3' },
  { id: 'pa4', name: '战略项目·新业务孵化',       managerId: 'u6' },
]
