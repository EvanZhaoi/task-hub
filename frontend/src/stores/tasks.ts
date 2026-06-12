import { create } from 'zustand'
import type { Task, TaskStatus, Difficulty } from '@/api/types'
import { MOCK_TASKS } from '@/mocks'

interface TasksState {
  list: Task[]
  filterStatus: TaskStatus | 'all'
  filterDifficulty: Difficulty | 'all'
  searchQuery: string
  page: number
  pageSize: number

  setFilter: (status: TaskStatus | 'all') => void
  setDifficultyFilter: (d: Difficulty | 'all') => void
  setSearch: (q: string) => void
  setPage: (p: number) => void
  getById: (id: string) => Task | undefined
  addTask: (task: Task) => void
  updateTask: (id: string, patch: Partial<Task>) => void
  setStatus: (id: string, status: TaskStatus) => void
}

export const useTasksStore = create<TasksState>((set, get) => ({
  list: [...MOCK_TASKS],

  filterStatus: 'all',
  filterDifficulty: 'all',
  searchQuery: '',
  page: 1,
  pageSize: 4,

  setFilter: (status) => set({ filterStatus: status, page: 1 }),
  setDifficultyFilter: (d) => set({ filterDifficulty: d, page: 1 }),
  setSearch: (q) => set({ searchQuery: q, page: 1 }),
  setPage: (p) => set({ page: p }),

  getById: (id) => get().list.find((t) => t.id === id),

  addTask: (task) => set((s) => ({ list: [task, ...s.list] })),

  updateTask: (id, patch) =>
    set((s) => ({
      list: s.list.map((t) => (t.id === id ? { ...t, ...patch, updatedAt: new Date().toISOString().slice(0, 10) } : t)),
    })),

  setStatus: (id, status) =>
    set((s) => ({
      list: s.list.map((t) => (t.id === id ? { ...t, status, updatedAt: new Date().toISOString().slice(0, 10) } : t)),
    })),
}))
