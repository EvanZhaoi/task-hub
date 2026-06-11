import { create } from 'zustand'
import type { ChangeLog } from '@/api/types'
import { MOCK_CHANGE_LOGS } from '@/mocks'

interface ChangeLogsState {
  logs: ChangeLog[]
  getByTaskId: (taskId: string) => ChangeLog[]
  addLog: (log: ChangeLog) => void
}

export const useChangeLogsStore = create<ChangeLogsState>((set, get) => ({
  logs: [...MOCK_CHANGE_LOGS],

  getByTaskId: (taskId) =>
    get()
      .logs.filter((l) => l.taskId === taskId)
      .sort((a, b) => b.createdAt.localeCompare(a.createdAt)),

  addLog: (log) => set((s) => ({ logs: [...s.logs, log] })),
}))
