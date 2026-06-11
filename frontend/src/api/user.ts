/**
 * 用户相关 API
 */
import api from './client'
import type { User, Task } from './types'

// 当前用户
export function getCurrentUser(): Promise<User> {
  return api.get('/users/me')
}

// 我发布的
export function getMyPublishedTasks(): Promise<Task[]> {
  return api.get('/users/me/tasks/published')
}

// 我投标的
export function getMyBiddedTasks(): Promise<Task[]> {
  return api.get('/users/me/tasks/bidded')
}

// 我接的
export function getMyAssignedTasks(): Promise<Task[]> {
  return api.get('/users/me/tasks/assigned')
}
