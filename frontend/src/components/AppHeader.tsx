import { Link, NavLink, useLocation, useNavigate } from 'react-router'
import { LogOut, ChevronDown, Layers } from 'lucide-react'
import { useUserStore } from '@/stores/user'
import { useTasksStore } from '@/stores/tasks'
import { MOCK_USERS } from '@/mocks'
import { UserAvatar } from '@/components/UserAvatar'
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
    <header className="sticky top-0 z-40 bg-background/80 backdrop-blur-md shadow-[0_1px_0_rgba(15,23,42,0.04)]">
      <div className="mx-auto max-w-7xl flex items-center justify-between px-6 h-14">
        <div className="flex items-center gap-8">
          <Link to="/" className="flex items-center gap-2 no-underline">
            <div className="flex h-7 w-7 items-center justify-center rounded-md bg-gradient-to-br from-primary to-purple-500 text-sm font-bold text-white shadow-sm">
              <Layers className="h-3.5 w-3.5" />
            </div>
            <h1 className="text-base font-semibold tracking-tight text-foreground">TaskHub</h1>
          </Link>

          <nav className="flex items-center gap-0.5 text-sm">
            {visibleNav.map((item) => (
              <NavLink
                key={item.path}
                to={item.path}
                className={cn(
                  'h-8 px-3 inline-flex items-center rounded-md no-underline transition-colors',
                  isActive(item.path)
                    ? 'bg-foreground/[0.06] text-foreground font-medium'
                    : 'text-muted-foreground hover:text-foreground hover:bg-foreground/[0.04]',
                )}
              >
                {item.label}
              </NavLink>
            ))}
          </nav>
        </div>

        {currentUser && (
          <div className="flex items-center gap-3">
            <div className="flex items-center gap-2 text-sm">
              <UserAvatar user={currentUser} size="sm" />
              <div className="hidden sm:flex flex-col leading-tight">
                <span className="text-foreground text-sm">{currentUser.name}</span>
                <span className="text-[11px] text-muted-foreground">{currentUser.department}</span>
              </div>
            </div>
            <div className="relative">
              <select
                value={currentUser.id}
                onChange={(e) => handleSwitch(e.target.value)}
                className="appearance-none h-8 pl-3 pr-7 rounded-md border border-input bg-background text-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-ring/40 focus:border-primary/50 transition-colors"
                title="切换身份（demo 用）"
              >
                {MOCK_USERS.map((u) => (
                  <option key={u.id} value={u.id}>
                    切换 · {u.name} · {roleLabel[u.role]}
                  </option>
                ))}
              </select>
              <ChevronDown className="absolute right-1.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
            </div>
            <Button variant="ghost" size="icon-sm" title="退出（demo）" className="text-muted-foreground">
              <LogOut className="h-3.5 w-3.5" />
            </Button>
          </div>
        )}
      </div>
    </header>
  )
}