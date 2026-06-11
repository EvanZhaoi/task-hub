import { create } from 'zustand'
import * as bidApi from '@/api/bid'
import type { Bid } from '@/api/types'

interface BidsState {
  bidsByTaskId: Record<string, Bid[]>
  loading: boolean
  error: string | null

  fetchBidsForTask: (taskId: string) => Promise<void>
  submitBid: (taskId: string, data: Parameters<typeof bidApi.submitBid>[1]) => Promise<Bid>
  acceptBid: (taskId: string, bidId: string) => Promise<void>
  withdrawBid: (taskId: string, bidId: string) => Promise<void>
}

export const useBidsStore = create<BidsState>((set) => ({
  bidsByTaskId: {},
  loading: false,
  error: null,

  async fetchBidsForTask(taskId) {
    set({ loading: true, error: null })
    try {
      const bids = await bidApi.listBidsForTask(taskId)
      set((s) => ({ bidsByTaskId: { ...s.bidsByTaskId, [taskId]: bids }, loading: false }))
    } catch (e) {
      set({ error: (e as Error).message, loading: false })
    }
  },

  async submitBid(taskId, data) {
    const bid = await bidApi.submitBid(taskId, data)
    set((s) => ({
      bidsByTaskId: {
        ...s.bidsByTaskId,
        [taskId]: [...(s.bidsByTaskId[taskId] ?? []), bid],
      },
    }))
    return bid
  },

  async acceptBid(taskId, bidId) {
    await bidApi.acceptBid(taskId, bidId)
    set((s) => ({
      bidsByTaskId: {
        ...s.bidsByTaskId,
        [taskId]: (s.bidsByTaskId[taskId] ?? []).map((b) =>
          b.id === bidId ? { ...b, status: 'ACCEPTED' as const } : { ...b, status: 'LOST' as const },
        ),
      },
    }))
  },

  async withdrawBid(taskId, bidId) {
    await bidApi.withdrawBid(taskId, bidId)
    set((s) => ({
      bidsByTaskId: {
        ...s.bidsByTaskId,
        [taskId]: (s.bidsByTaskId[taskId] ?? []).map((b) =>
          b.id === bidId ? { ...b, status: 'WITHDRAWN' as const } : b,
        ),
      },
    }))
  },
}))
