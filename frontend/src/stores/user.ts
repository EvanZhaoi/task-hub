/**
 * 用户 Store
 * 当前用户信息、角色判断、登录态管理
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User, UserRole } from '@/api/types'
import { getCurrentUser } from '@/api/user'

const STORAGE_KEY = 'taskhub.currentUser'
const STORAGE_TOKEN_KEY = 'taskhub.token'

export const useUserStore = defineStore('user', () => {
  // ---- state ----
  const currentUser = ref<User | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // ---- getters ----
  const isLoggedIn = computed(() => currentUser.value !== null)
  const isBoss = computed(() => currentUser.value?.role === 'boss')
  const isDeveloper = computed(() => currentUser.value?.role === 'developer')
  const isPublisher = computed(() => currentUser.value?.role === 'publisher')
  const userId = computed(() => currentUser.value?.id ?? '')
  const userName = computed(() => currentUser.value?.name ?? '')

  // ---- actions ----
  async function fetchCurrentUser() {
    loading.value = true
    error.value = null
    try {
      const user = await getCurrentUser()
      currentUser.value = user
      saveToStorage()
      return user
    } catch (e) {
      error.value = (e as Error).message
      throw e
    } finally {
      loading.value = false
    }
  }

  function setUser(user: User | null) {
    currentUser.value = user
    saveToStorage()
  }

  function hasRole(...roles: UserRole[]): boolean {
    return currentUser.value ? roles.includes(currentUser.value.role) : false
  }

  function setToken(token: string) {
    localStorage.setItem(STORAGE_TOKEN_KEY, token)
  }

  function clearToken() {
    localStorage.removeItem(STORAGE_TOKEN_KEY)
  }

  function saveToStorage() {
    if (currentUser.value) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(currentUser.value))
      localStorage.setItem('taskhub.isBoss', String(currentUser.value.role === 'boss'))
    } else {
      localStorage.removeItem(STORAGE_KEY)
      localStorage.removeItem('taskhub.isBoss')
    }
  }

  function restoreFromStorage() {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored) {
      try {
        currentUser.value = JSON.parse(stored)
      } catch {
        localStorage.removeItem(STORAGE_KEY)
      }
    }
  }

  function logout() {
    currentUser.value = null
    clearToken()
    saveToStorage()
  }

  return {
    // state
    currentUser,
    loading,
    error,
    // getters
    isLoggedIn,
    isBoss,
    isDeveloper,
    isPublisher,
    userId,
    userName,
    // actions
    fetchCurrentUser,
    setUser,
    hasRole,
    setToken,
    clearToken,
    restoreFromStorage,
    logout,
  }
})
