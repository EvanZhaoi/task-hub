import { api } from './client'
import type { Bid, BidRequest } from './types'

export const listBidsForTask = (taskId: string) =>
  api.get<unknown, Bid[]>(`/tasks/${taskId}/bids`)

export const submitBid = (taskId: string, data: BidRequest) =>
  api.post<unknown, Bid>(`/tasks/${taskId}/bids`, data)

export const acceptBid = (taskId: string, bidId: string) =>
  api.post<unknown, void>(`/tasks/${taskId}/bids/${bidId}/accept`)

export const withdrawBid = (taskId: string, bidId: string) =>
  api.post<unknown, void>(`/tasks/${taskId}/bids/${bidId}/withdraw`)
