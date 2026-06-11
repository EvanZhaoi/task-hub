import { useState } from 'react'
import { useUserStore } from '@/stores/user'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

type Tab = 'published' | 'bidded' | 'assigned'

export function ProfileView() {
  const currentUser = useUserStore((s) => s.currentUser)
  const [activeTab, setActiveTab] = useState<Tab>('published')

  const tabs: { key: Tab; label: string; count: number }[] = [
    { key: 'published', label: '我发布的', count: 0 },
    { key: 'bidded',    label: '我投标的', count: 0 },
    { key: 'assigned',  label: '我接的',   count: 0 },
  ]

  return (
    <div className="mx-auto max-w-5xl px-6 py-8">
      {currentUser && (
        <div className="mb-6 flex items-center gap-4">
          <div
            className={cn(
              'flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br text-sm font-medium text-white',
              currentUser.role === 'publisher' && 'from-orange-500 to-orange-400',
              currentUser.role === 'boss' && 'from-gray-900 to-gray-700',
              currentUser.role === 'developer' && 'from-primary to-purple-500',
            )}
          >
            {currentUser.name.charAt(0)}
          </div>
          <div>
            <h1 className="text-2xl font-semibold text-foreground">{currentUser.name}</h1>
            <p className="mt-1 text-sm text-muted-foreground">{currentUser.department}</p>
          </div>
        </div>
      )}

      <div className="mb-6 flex gap-1 border-b border-border">
        {tabs.map((tab) => (
          <Button
            key={tab.key}
            variant={activeTab === tab.key ? 'default' : 'ghost'}
            size="sm"
            onClick={() => setActiveTab(tab.key)}
          >
            {tab.label} ({tab.count})
          </Button>
        ))}
      </div>

      <Card>
        <CardContent className="py-12 text-center text-muted-foreground">
          <p>个人主页</p>
          <p className="mt-2 text-xs">Phase 1 待实现：3 个 Tab 的任务列表 + 我的任务时间线（甘特图）</p>
        </CardContent>
      </Card>
    </div>
  )
}
