/**
 * TaskHub 共享类型定义
 * 与后端 DTO 保持一致
 */

// ========== 枚举 ==========
export type TaskStatus = 'DRAFT' | 'OPEN' | 'ASSIGNED' | 'COMPLETED' | 'FAILED' | 'CANCELLED'
export type BidStatus = 'ACTIVE' | 'WITHDRAWN' | 'ACCEPTED' | 'LOST'
export type ChangeType = 'AMOUNT' | 'DELIVERY' | 'DESCRIPTION' | 'EXTENSION' | 'CANCEL'
export type UserRole = 'developer' | 'publisher' | 'boss'

// ========== 实体 ==========
export interface Task {
  id: string
  title: string
  description?: string
  paymentAccountId: string
  budget: number
  finalAmount?: number
  expectedDelivery: string
  finalDelivery?: string
  biddingDeadline?: string
  status: TaskStatus
  isDirect: boolean
  createdBy: string
  assignedBidId?: string
  createdAt: string
  updatedAt: string
}

export interface Bid {
  id: string
  taskId: string
  bidderId: string
  amount: number
  deliveryDate: string
  proposal?: string
  status: BidStatus
  collaborators?: string[] // user_id 数组
  createdAt: string
}

export interface Attachment {
  id: string
  taskId: string
  fileName: string
  fileSize: number
  mimeType?: string
  uploadedBy: string
  uploadedAt: string
}

export interface ChangeLog {
  id: string
  taskId: string
  changeType: ChangeType
  oldValue?: Record<string, unknown>
  newValue?: Record<string, unknown>
  reason?: string
  agreedBy?: string[]
  createdBy: string
  createdAt: string
}

export interface User {
  id: string
  name: string
  role: UserRole
  department: string
  email?: string
  avatar?: string
}

export interface PaymentAccount {
  id: string
  name: string
  managerId: string
}

// ========== 请求 DTO ==========
export interface CreateTaskRequest {
  title: string
  description?: string
  paymentAccountId: string
  budget: number
  expectedDelivery: string
  biddingDeadline?: string
}

export interface DirectAssignRequest extends CreateTaskRequest {
  assigneeId: string
}

export interface BidRequest {
  amount: number
  deliveryDate: string
  proposal?: string
  collaborators?: string[]
}

export interface ExtensionRequest {
  newDelivery: string
  reason: string
}

export interface TaskQueryRequest {
  page?: number
  size?: number
  q?: string
  status?: TaskStatus | 'all'
}

// ========== 响应 ==========
export interface PageResponse<T> {
  items: T[]
  total: number
  page: number
  size: number
  totalPages: number
}

export interface ApiResponse<T> {
  code: number
  message: string
  data: T
}
