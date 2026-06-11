import type { Attachment } from '@/api/types'

export const MOCK_ATTACHMENTS: Attachment[] = [
  { id: 'a1', taskId: 't1', fileName: '登录页设计稿.fig',        fileSize: 2450000,  mimeType: 'application/figma', uploadedBy: 'u5', uploadedAt: '2026-06-10' },
  { id: 'a2', taskId: 't1', fileName: '现有登录页截图.png',       fileSize: 380000,   mimeType: 'image/png',        uploadedBy: 'u5', uploadedAt: '2026-06-10' },
  { id: 'a3', taskId: 't3', fileName: 'CRM 接口文档.pdf',         fileSize: 580000,   mimeType: 'application/pdf',  uploadedBy: 'u4', uploadedAt: '2026-06-05' },
  { id: 'a4', taskId: 't3', fileName: 'CRM 源码（只读）tar.gz',   fileSize: 12500000, mimeType: 'application/gzip', uploadedBy: 'u4', uploadedAt: '2026-06-05' },
  { id: 'a5', taskId: 't7', fileName: '客户名单示例.csv',         fileSize: 12000,    mimeType: 'text/csv',         uploadedBy: 'u6', uploadedAt: '2026-06-11' },
]
