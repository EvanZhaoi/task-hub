import { createBrowserRouter, Navigate, Outlet, type RouteObject } from 'react-router'
import { AppHeader } from '@/components/AppHeader'

/**
 * 路由配置（React Router 7）
 * - createBrowserRouter 走 v7 数据路由模式
 * - lazy 字段实现路由级 code splitting
 */
const routes: RouteObject[] = [
  {
    path: '/',
    element: <Layout />,
    children: [
      { index: true, lazy: () => import('@/views/HomeView').then((m) => ({ Component: m.HomeView })) },
      { path: 'task/:id', lazy: () => import('@/views/TaskDetailView').then((m) => ({ Component: m.TaskDetailView })) },
      { path: 'create', lazy: () => import('@/views/CreateTaskView').then((m) => ({ Component: m.CreateTaskView })) },
      { path: 'profile', lazy: () => import('@/views/ProfileView').then((m) => ({ Component: m.ProfileView })) },
      {
        path: 'boss',
        lazy: () => import('@/views/BossGanttView').then((m) => ({ Component: m.BossGanttView })),
      },
      { path: '*', element: <Navigate to="/" replace /> },
    ],
  },
]

function Layout() {
  return (
    <div className="min-h-screen bg-gray-50">
      <AppHeader />
      <main>
        <Outlet />
      </main>
    </div>
  )
}

export const router = createBrowserRouter(routes)
