/**
 * 附件相关 API
 */
import api from './client'
import type { Attachment } from './types'

// 任务附件列表
export function listAttachmentsForTask(taskId: string): Promise<Attachment[]> {
  return api.get(`/tasks/${taskId}/attachments`)
}

// 上传附件
export function uploadAttachment(taskId: string, file: File): Promise<Attachment> {
  const formData = new FormData()
  formData.append('file', file)
  return api.post(`/tasks/${taskId}/attachments`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

// 下载附件
export function getAttachmentDownloadUrl(id: string): string {
  return `${import.meta.env.VITE_API_BASE || '/api'}/attachments/${id}/download`
}
