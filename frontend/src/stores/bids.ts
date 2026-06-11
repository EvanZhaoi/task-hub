/**
 * 投标 Store
 * 投标列表、提交投标、选标
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Bid } from '@/api/types'
import * as bidApi from '@/api/bid'

export const useBidsStore = defineStore('bids', () => {
  // ---- state ----
  // 按 taskId 分组缓存
  const bidsByTaskId = ref<Record<string, Bid[]>>({})
  const loading = ref(false)
  const error = ref<string | null>(null)

  // ---- actions ----
  async function fetchBidsForTask(taskId: string) {
    loading.value = true
    error.value = null
    try {
      const bids = await bidApi.listBidsForTask(taskId)
      bidsByTaskId.value[taskId] = bids
      return bids
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function submitBid(taskId: string, data: Parameters<typeof bidApi.submitBid>[1]) {
    const bid = await bidApi.submitBid(taskId, data)
    if (bidsByTaskId.value[taskId]) {
      bidsByTaskId.value[taskId].push(bid)
    } else {
      bidsByTaskId.value[taskId] = [bid]
    }
    return bid
  }

  async function acceptBid(taskId: string, bidId: string) {
    await bidApi.acceptBid(taskId, bidId)
    // 刷新该任务的投标列表
    if (bidsByTaskId.value[taskId]) {
      bidsByTaskId.value[taskId] = bidsByTaskId.value[taskId].map((b) =>
        b.id === bidId ? { ...b, status: 'ACCEPTED' as const } : { ...b, status: 'LOST' as const },
      )
    }
  }

  async function withdrawBid(taskId: string, bidId: string) {
    await bidApi.withdrawBid(taskId, bidId)
    if (bidsByTaskId.value[taskId]) {
      bidsByTaskId.value[taskId] = bidsByTaskId.value[taskId].map((b) =>
        b.id === bidId ? { ...b, status: 'WITHDRAWN' as const } : b,
      )
    }
  }

  function clearCache(taskId?: string) {
    if (taskId) {
      delete bidsByTaskId.value[taskId]
    } else {
      bidsByTaskId.value = {}
    }
  }

  return {
    bidsByTaskId,
    loading,
    error,
    fetchBidsForTask,
    submitBid,
    acceptBid,
    withdrawBid,
    clearCache,
  }
})
