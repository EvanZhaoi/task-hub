/** TaskHub 共享类型（与后端 DTO 对齐） */

export type TaskStatus = 'DRAFT' | 'OPEN' | 'ASSIGNED' | 'COMPLETED' | 'FAILED' | 'CANCELLED'
export type BidStatus = 'ACTIVE' | 'WITHDRAWN' | 'ACCEPTED' | 'LOST'
export type ChangeType = 'AMOUNT' | 'DELIVERY' | 'DESCRIPTION' | 'EXTENSION' | 'CANCEL'
export type UserRole = 'developer' | 'publisher' | 'boss'
export type Complexity = 'LOW' | 'MEDIUM' | 'HIGH'

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
  complexity: Complexity
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
  collaborators?: string[]
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

export interface CreateTaskRequest {
  title: string
  description?: string
  paymentAccountId: string
  budget: number
  expectedDelivery: string
  biddingDeadline?: string
  complexity?: Complexity
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

export interface PageResponse<T> {
  items: T[]
  total: number
  page: number
  size: number
  totalPages: number
}
