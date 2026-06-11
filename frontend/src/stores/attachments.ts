import { create } from 'zustand'
import type { Attachment } from '@/api/types'
import { MOCK_ATTACHMENTS } from '@/mocks'

interface AttachmentsState {
  list: Attachment[]
  getByTaskId: (taskId: string) => Attachment[]
}

export const useAttachmentsStore = create<AttachmentsState>()((_set, get) => ({
  list: [...MOCK_ATTACHMENTS],
  getByTaskId: (taskId) => get().list.filter((a) => a.taskId === taskId),
}))
