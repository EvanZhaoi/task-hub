/**
 * 投标相关 API
 */
import api from './client'
import type { Bid, BidRequest } from './types'

// 投标列表
export function listBidsForTask(taskId: string): Promise<Bid[]> {
  return api.get(`/tasks/${taskId}/bids`)
}

// 投标
export function submitBid(taskId: string, data: BidRequest): Promise<Bid> {
  return api.post(`/tasks/${taskId}/bids`, data)
}

// 选标
export function acceptBid(taskId: string, bidId: string): Promise<void> {
  return api.post(`/tasks/${taskId}/bids/${bidId}/accept`)
}

// 撤回投标
export function withdrawBid(taskId: string, bidId: string): Promise<void> {
  return api.post(`/tasks/${taskId}/bids/${bidId}/withdraw`)
}
