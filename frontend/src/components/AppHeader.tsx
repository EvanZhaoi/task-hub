import { Link, NavLink, useLocation, useNavigate } from 'react-router'
import { LogOut, User as UserIcon } from 'lucide-react'
import { useUserStore } from '@/stores/user'
import { useTasksStore } from '@/stores/tasks'
import { MOCK_USERS } from '@/mocks'
import { Button } from '@/components/ui/button'
import { roleLabel } from '@/utils/format'
import { cn } from '@/lib/utils'
import type { UserRole } from '@/api/types'

export function AppHeader() {
  const location = useLocation()
  const navigate = useNavigate()
  const currentUser = useUserStore((s) => s.currentUser)
  const setUser = useUserStore((s) => s.setUser)
  const reset = useTasksStore((s) => s.setFilter)

  const navItems: { label: string; path: string; roles?: UserRole[] }[] = [
    { label: '任务大厅', path: '/' },
    { label: '发布任务', path: '/create', roles: ['publisher', 'boss'] },
    { label: '我的', path: '/profile' },
    { label: '老板视图', path: '/boss', roles: ['boss'] },
  ]

  const visibleNav = navItems.filter(
    (item) => !item.roles || (currentUser && item.roles.includes(currentUser.role)),
  )

  const isActive = (path: string) =>
    path === '/' ? location.pathname === '/' : location.pathname.startsWith(path)

  const handleSwitch = (userId: string) => {
    const user = MOCK_USERS.find((u) => u.id === userId)
    if (user) {
      setUser(user)
      reset('all')
      if (location.pathname === '/boss' && user.role !== 'boss') navigate('/')
      else if (location.pathname === '/create' && user.role === 'developer') navigate('/')
    }
  }

  return (
    <header className="sticky top-0 z-40 flex items-center justify-between border-b border-border bg-background/95 px-6 py-3 backdrop-blur">
      <div className="flex items-center gap-8">
        <Link to="/" className="flex items-center gap-2 no-underline">
          <div className="flex h-7 w-7 items-center justify-center rounded-md bg-primary text-sm font-bold text-primary-foreground">
            T
          </div>
          <h1 className="text-base font-semibold text-foreground">TaskHub</h1>
        </Link>

        <nav className="flex gap-1 text-sm">
          {visibleNav.map((item) => (
            <NavLink
              key={item.path}
              to={item.path}
              className={cn(
                'rounded-md px-3 py-1.5 text-muted-foreground no-underline transition-colors hover:bg-accent hover:text-accent-foreground',
                isActive(item.path) && 'bg-accent text-accent-foreground font-medium',
              )}
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
      </div>

      <div className="flex items-center gap-3">
        {currentUser && (
          <>
            <div className="flex items-center gap-2 text-sm">
              <UserIcon className="h-4 w-4 text-muted-foreground" />
              <span className="text-foreground">{currentUser.name}</span>
              <span className="text-xs text-muted-foreground">· {currentUser.department}</span>
            </div>
            <select
              value={currentUser.id}
              onChange={(e) => handleSwitch(e.target.value)}
              className="h-9 rounded-md border border-input bg-background px-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
              title="切换身份（demo 用）"
            >
              {MOCK_USERS.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name} · {roleLabel[u.role]}
                </option>
              ))}
            </select>
            <Button variant="ghost" size="sm" title="退出（demo）">
              <LogOut className="h-4 w-4" />
            </Button>
          </>
        )}
      </div>
    </header>
  )
}
