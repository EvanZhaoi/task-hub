import { create } from 'zustand'
import * as taskApi from '@/api/task'
import type { Task, TaskStatus } from '@/api/types'

interface TasksState {
  list: Task[]
  detail: Task | null
  total: number
  page: number
  size: number
  totalPages: number
  loading: boolean
  error: string | null
  filterStatus: TaskStatus | 'all'
  searchQuery: string

  fetchList: (params?: { page?: number; size?: number }) => Promise<void>
  fetchDetail: (id: string) => Promise<void>
  createTask: (data: Parameters<typeof taskApi.createTask>[0]) => Promise<Task>
  completeTask: (id: string) => Promise<void>
  cancelTask: (id: string) => Promise<void>
  setFilter: (status: TaskStatus | 'all') => void
  setSearch: (q: string) => void
  reset: () => void
}

export const useTasksStore = create<TasksState>((set, get) => ({
  list: [],
  detail: null,
  total: 0,
  page: 1,
  size: 10,
  totalPages: 1,
  loading: false,
  error: null,
  filterStatus: 'all',
  searchQuery: '',

  async fetchList(params = {}) {
    set({ loading: true, error: null })
    try {
      const res = await taskApi.listTasks({
        page: params.page ?? get().page,
        size: params.size ?? get().size,
        q: get().searchQuery,
        status: get().filterStatus,
      })
      set({
        list: res.items,
        total: res.total,
        page: res.page,
        size: res.size,
        totalPages: res.totalPages,
        loading: false,
      })
    } catch (e) {
      set({ error: (e as Error).message, loading: false })
    }
  },

  async fetchDetail(id) {
    set({ loading: true, error: null })
    try {
      const task = await taskApi.getTask(id)
      set({ detail: task, loading: false })
    } catch (e) {
      set({ error: (e as Error).message, loading: false })
    }
  },

  async createTask(data) {
    const task = await taskApi.createTask(data)
    set((s) => ({ list: [task, ...s.list] }))
    return task
  },

  async completeTask(id) {
    await taskApi.completeTask(id)
    set((s) => ({
      detail: s.detail?.id === id ? { ...s.detail, status: 'COMPLETED' as const } : s.detail,
    }))
  },

  async cancelTask(id) {
    await taskApi.cancelTask(id)
    set((s) => ({
      detail: s.detail?.id === id ? { ...s.detail, status: 'CANCELLED' as const } : s.detail,
    }))
  },

  setFilter: (status) => set({ filterStatus: status, page: 1 }),
  setSearch: (q) => set({ searchQuery: q, page: 1 }),
  reset: () => set({ list: [], detail: null, page: 1, total: 0 }),
}))
