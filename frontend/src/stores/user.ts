import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type { User, UserRole } from '@/api/types'
import { getCurrentUser } from '@/api/user'

interface UserState {
  currentUser: User | null
  loading: boolean
  error: string | null
  // getters
  isLoggedIn: () => boolean
  isBoss: () => boolean
  isDeveloper: () => boolean
  isPublisher: () => boolean
  // actions
  fetchCurrentUser: () => Promise<void>
  setUser: (user: User | null) => void
  hasRole: (...roles: UserRole[]) => boolean
  logout: () => void
}

export const useUserStore = create<UserState>()(
  persist(
    (set, get) => ({
      currentUser: null,
      loading: false,
      error: null,

      isLoggedIn: () => get().currentUser !== null,
      isBoss: () => get().currentUser?.role === 'boss',
      isDeveloper: () => get().currentUser?.role === 'developer',
      isPublisher: () => get().currentUser?.role === 'publisher',

      async fetchCurrentUser() {
        set({ loading: true, error: null })
        try {
          const user = await getCurrentUser()
          set({ currentUser: user, loading: false })
        } catch (e) {
          set({ error: (e as Error).message, loading: false })
        }
      },

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
    {
      name: 'taskhub-user',
      partialize: (state) => ({ currentUser: state.currentUser }),
    },
  ),
)
