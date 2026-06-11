/**
 * 任务相关 API
 */
import api from './client'
import type {
  Task,
  CreateTaskRequest,
  DirectAssignRequest,
  ExtensionRequest,
  TaskQueryRequest,
  PageResponse,
} from './types'

// 任务大厅（分页 + 搜索 + 筛选）
export function listTasks(params: TaskQueryRequest = {}): Promise<PageResponse<Task>> {
  return api.get('/tasks', { params })
}

// 任务详情
export function getTask(id: string): Promise<Task> {
  return api.get(`/tasks/${id}`)
}

// 发布任务（招标）
export function createTask(data: CreateTaskRequest): Promise<Task> {
  return api.post('/tasks', data)
}

// 直接指名
export function directAssign(data: DirectAssignRequest): Promise<Task> {
  return api.post('/tasks/direct', data)
}

// 取消任务
export function cancelTask(id: string): Promise<void> {
  return api.post(`/tasks/${id}/cancel`)
}

// 确认完成
export function completeTask(id: string): Promise<void> {
  return api.post(`/tasks/${id}/complete`)
}

// 复制新建（流标后）
export function cloneTask(id: string): Promise<Task> {
  return api.post(`/tasks/${id}/clone`)
}

// 申请延期
export function requestExtension(id: string, data: ExtensionRequest): Promise<void> {
  return api.post(`/tasks/${id}/extend`, data)
}
