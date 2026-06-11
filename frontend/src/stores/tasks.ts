/**
 * 任务 Store
 * 任务列表、详情、CRUD actions
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Task, TaskQueryRequest, TaskStatus } from '@/api/types'
import * as taskApi from '@/api/task'

export const useTasksStore = defineStore('tasks', () => {
  // ---- state ----
  const list = ref<Task[]>([])
  const detail = ref<Task | null>(null)
  const total = ref(0)
  const page = ref(1)
  const size = ref(10)
  const totalPages = ref(1)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // 筛选状态
  const filterStatus = ref<TaskStatus | 'all'>('all')
  const searchQuery = ref('')

  // ---- getters ----
  const hasMore = computed(() => page.value < totalPages.value)

  // ---- actions ----
  async function fetchList(params: Partial<TaskQueryRequest> = {}) {
    loading.value = true
    error.value = null
    try {
      const res = await taskApi.listTasks({
        page: params.page ?? page.value,
        size: params.size ?? size.value,
        q: params.q ?? searchQuery.value,
        status: params.status ?? filterStatus.value,
      })
      list.value = res.items
      total.value = res.total
      page.value = res.page
      size.value = res.size
      totalPages.value = res.totalPages
      return res
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchDetail(id: string) {
    loading.value = true
    error.value = null
    try {
      detail.value = await taskApi.getTask(id)
      return detail.value
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function createTask(data: Parameters<typeof taskApi.createTask>[0]) {
    const task = await taskApi.createTask(data)
    list.value.unshift(task)
    return task
  }

  async function directAssign(data: Parameters<typeof taskApi.directAssign>[0]) {
    const task = await taskApi.directAssign(data)
    list.value.unshift(task)
    return task
  }

  async function completeTask(id: string) {
    await taskApi.completeTask(id)
    if (detail.value?.id === id) {
      detail.value.status = 'COMPLETED'
    }
  }

  async function cancelTask(id: string) {
    await taskApi.cancelTask(id)
    if (detail.value?.id === id) {
      detail.value.status = 'CANCELLED'
    }
  }

  function setFilter(status: TaskStatus | 'all') {
    filterStatus.value = status
    page.value = 1
  }

  function setSearch(q: string) {
    searchQuery.value = q
    page.value = 1
  }

  function resetList() {
    list.value = []
    detail.value = null
    page.value = 1
    total.value = 0
  }

  return {
    // state
    list,
    detail,
    total,
    page,
    size,
    totalPages,
    loading,
    error,
    filterStatus,
    searchQuery,
    // getters
    hasMore,
    // actions
    fetchList,
    fetchDetail,
    createTask,
    directAssign,
    completeTask,
    cancelTask,
    setFilter,
    setSearch,
    resetList,
  }
})
