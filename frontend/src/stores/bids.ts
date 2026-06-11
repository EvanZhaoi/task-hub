import { create } from 'zustand'
import type { Bid } from '@/api/types'
import { MOCK_BIDS } from '@/mocks'

interface BidsState {
  bids: Bid[]
  getByTaskId: (taskId: string) => Bid[]
  addBid: (bid: Bid) => void
  setStatus: (bidId: string, status: Bid['status']) => void
  markOthersLost: (taskId: string, acceptedBidId: string) => void
}

export const useBidsStore = create<BidsState>((set, get) => ({
  bids: [...MOCK_BIDS],

  getByTaskId: (taskId) => get().bids.filter((b) => b.taskId === taskId),

  addBid: (bid) => set((s) => ({ bids: [...s.bids, bid] })),

  setStatus: (bidId, status) =>
    set((s) => ({ bids: s.bids.map((b) => (b.id === bidId ? { ...b, status } : b)) })),

  markOthersLost: (taskId, acceptedBidId) =>
    set((s) => ({
      bids: s.bids.map((b) => {
        if (b.taskId !== taskId) return b
        if (b.id === acceptedBidId) return { ...b, status: 'ACCEPTED' as const }
        if (b.status === 'ACTIVE') return { ...b, status: 'LOST' as const }
        return b
      }),
    })),
}))
