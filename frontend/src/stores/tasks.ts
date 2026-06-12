import { create } from 'zustand'
import type { Task, TaskStatus, Complexity } from '@/api/types'
import { MOCK_TASKS } from '@/mocks'

export type SortBy = 'default' | 'amount-desc' | 'amount-asc'

interface TasksState {
  list: Task[]
  filterStatus: TaskStatus | 'all'
  filterComplexity: Complexity | 'all'
  filterPublisher: string | 'all'  // user ID
  filterAssignee: string | 'all'   // user ID
  sortBy: SortBy
  searchQuery: string
  page: number
  pageSize: number

  setFilter: (status: TaskStatus | 'all') => void
  setComplexityFilter: (c: Complexity | 'all') => void
  setPublisherFilter: (id: string | 'all') => void
  setAssigneeFilter: (id: string | 'all') => void
  setSortBy: (s: SortBy) => void
  setSearch: (q: string) => void
  setPage: (p: number) => void
  resetFilters: () => void
  getById: (id: string) => Task | undefined
  addTask: (task: Task) => void
  updateTask: (id: string, patch: Partial<Task>) => void
  setStatus: (id: string, status: TaskStatus) => void
}

export const useTasksStore = create<TasksState>((set, get) => ({
  list: [...MOCK_TASKS],

  filterStatus: 'all',
  filterComplexity: 'all',
  filterPublisher: 'all',
  filterAssignee: 'all',
  sortBy: 'default',
  searchQuery: '',
  page: 1,
  pageSize: 4,

  setFilter: (status) => set({ filterStatus: status, page: 1 }),
  setComplexityFilter: (c) => set({ filterComplexity: c, page: 1 }),
  setPublisherFilter: (id) => set({ filterPublisher: id, page: 1 }),
  setAssigneeFilter: (id) => set({ filterAssignee: id, page: 1 }),
  setSortBy: (s) => set({ sortBy: s, page: 1 }),
  setSearch: (q) => set({ searchQuery: q, page: 1 }),
  setPage: (p) => set({ page: p }),
  resetFilters: () => set({
    filterStatus: 'all',
    filterComplexity: 'all',
    filterPublisher: 'all',
    filterAssignee: 'all',
    sortBy: 'default',
    searchQuery: '',
    page: 1,
  }),

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
