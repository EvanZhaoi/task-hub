import { Link, useLocation, useNavigate } from 'react-router'
import { LogOut, User as UserIcon } from 'lucide-react'
import { useUserStore } from '@/stores/user'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

export function AppHeader() {
  const location = useLocation()
  const navigate = useNavigate()
  const currentUser = useUserStore((s) => s.currentUser)
  const isBoss = useUserStore((s) => s.isBoss())
  const logout = useUserStore((s) => s.logout)

  const navItems = [
    { label: '任务大厅', path: '/' },
    { label: '发布任务', path: '/create' },
    { label: '我的', path: '/profile' },
    ...(isBoss ? [{ label: '老板视图', path: '/boss' }] : []),
  ]

  const isActive = (path: string) =>
    path === '/' ? location.pathname === '/' : location.pathname.startsWith(path)

  const handleLogout = () => {
    logout()
    navigate('/')
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
          {navItems.map((item) => (
            <Link
              key={item.path}
              to={item.path}
              className={cn(
                'rounded-md px-3 py-1.5 text-muted-foreground no-underline transition-colors hover:bg-accent hover:text-accent-foreground',
                isActive(item.path) && 'bg-accent text-accent-foreground font-medium',
              )}
            >
              {item.label}
            </Link>
          ))}
        </nav>
      </div>

      <div className="flex items-center gap-3">
        {currentUser ? (
          <>
            <div className="flex items-center gap-2 text-sm">
              <UserIcon className="h-4 w-4 text-muted-foreground" />
              <span className="text-foreground">{currentUser.name}</span>
              <span className="text-xs text-muted-foreground">· {currentUser.department}</span>
            </div>
            <Button variant="ghost" size="sm" onClick={handleLogout}>
              <LogOut className="h-4 w-4" />
              退出
            </Button>
          </>
        ) : (
          <Button variant="default" size="sm">登录</Button>
        )}
      </div>
    </header>
  )
}
