import { api } from './client'
import type { User, Task } from './types'

export const getCurrentUser = () => api.get<unknown, User>('/users/me')
export const getMyPublishedTasks = () => api.get<unknown, Task[]>('/users/me/tasks/published')
export const getMyBiddedTasks = () => api.get<unknown, Task[]>('/users/me/tasks/bidded')
export const getMyAssignedTasks = () => api.get<unknown, Task[]>('/users/me/tasks/assigned')
