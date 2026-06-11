import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/HomeView.vue'),
    meta: { title: '任务大厅' },
  },
  {
    path: '/task/:id',
    name: 'task-detail',
    component: () => import('@/views/TaskDetailView.vue'),
    meta: { title: '任务详情' },
  },
  {
    path: '/create',
    name: 'create-task',
    component: () => import('@/views/CreateTaskView.vue'),
    meta: { title: '发布任务' },
  },
  {
    path: '/profile',
    name: 'profile',
    component: () => import('@/views/ProfileView.vue'),
    meta: { title: '我的' },
  },
  {
    path: '/boss',
    name: 'boss',
    component: () => import('@/views/BossGanttView.vue'),
    meta: { title: '老板视图', requireBoss: true },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
  scrollBehavior(_to, _from, savedPosition) {
    return savedPosition || { top: 0 }
  },
})

router.beforeEach((to, _from, next) => {
  // 设置页面标题
  const title = (to.meta.title as string) || ''
  document.title = title ? `${title} · TaskHub` : 'TaskHub'

  // Boss 视图权限校验
  if (to.meta.requireBoss) {
    const isBoss = localStorage.getItem('taskhub.isBoss') === 'true'
    if (!isBoss) {
      next({ name: 'home' })
      return
    }
  }

  next()
})

export default router
