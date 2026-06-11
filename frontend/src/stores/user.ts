import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type { User, UserRole } from '@/api/types'
import { MOCK_USERS } from '@/mocks'

interface UserState {
  currentUser: User | null
  isBoss: () => boolean
  isDeveloper: () => boolean
  isPublisher: () => boolean
  setUser: (user: User | null) => void
  hasRole: (...roles: UserRole[]) => boolean
  logout: () => void
}

export const useUserStore = create<UserState>()(
  persist(
    (set, get) => ({
      currentUser: MOCK_USERS[0], // 默认是李雷（开发者）

      isBoss: () => get().currentUser?.role === 'boss',
      isDeveloper: () => get().currentUser?.role === 'developer',
      isPublisher: () => get().currentUser?.role === 'publisher',

      setUser: (user) => set({ currentUser: user }),

      hasRole: (...roles) => {
        const u = get().currentUser
        return u ? roles.includes(u.role) : false
      },

      logout: () => {
        set({ currentUser: null })
        localStorage.removeItem('taskhub.token')
      },
    }),
    { name: 'taskhub-user' },
  ),
)
