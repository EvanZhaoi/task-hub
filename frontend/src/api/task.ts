import { api } from './client'
import type { Task, CreateTaskRequest, DirectAssignRequest, PageResponse } from './types'

export const listTasks = (params: { page?: number; size?: number; q?: string; status?: string } = {}) =>
  api.get<unknown, PageResponse<Task>>('/tasks', { params })

export const getTask = (id: string) =>
  api.get<unknown, Task>(`/tasks/${id}`)

export const createTask = (data: CreateTaskRequest) =>
  api.post<unknown, Task>('/tasks', data)

export const directAssign = (data: DirectAssignRequest) =>
  api.post<unknown, Task>('/tasks/direct', data)

export const cancelTask = (id: string) =>
  api.post<unknown, void>(`/tasks/${id}/cancel`)

export const completeTask = (id: string) =>
  api.post<unknown, void>(`/tasks/${id}/complete`)
