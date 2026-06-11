<script setup lang="ts">
/**
 * AppHeader
 * 演示：shadcn-vue Button 的 variant + size 用法
 */
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LogOut, User as UserIcon } from '@lucide/vue'
import { useUserStore } from '@/stores/user'
import Button from '@/components/ui/Button.vue'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const navItems = computed(() => [
  { name: 'home', label: '任务大厅', path: '/' },
  { name: 'create-task', label: '发布任务', path: '/create' },
  { name: 'profile', label: '我的', path: '/profile' },
  ...(userStore.isBoss ? [{ name: 'boss', label: '老板视图', path: '/boss' }] : []),
])

function isActive(path: string) {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

function logout() {
  userStore.logout()
  router.push('/')
}
</script>

<template>
  <header
    class="sticky top-0 z-40 flex items-center justify-between border-b border-border bg-background/95 px-6 py-3 backdrop-blur"
  >
    <!-- 左：Logo + 导航 -->
    <div class="flex items-center gap-8">
      <router-link to="/" class="flex items-center gap-2 no-underline">
        <div
          class="flex h-7 w-7 items-center justify-center rounded-md bg-primary text-sm font-bold text-primary-foreground"
        >
          T
        </div>
        <h1 class="text-base font-semibold text-foreground">TaskHub</h1>
      </router-link>

      <nav class="flex gap-1 text-sm">
        <router-link
          v-for="item in navItems"
          :key="item.name"
          :to="item.path"
          class="rounded-md px-3 py-1.5 text-muted-foreground no-underline transition-colors hover:bg-accent hover:text-accent-foreground"
          :class="{
            'bg-accent text-accent-foreground font-medium': isActive(item.path),
          }"
        >
          {{ item.label }}
        </router-link>
      </nav>
    </div>

    <!-- 右：用户区（演示 shadcn-vue Button 变体） -->
    <div class="flex items-center gap-3">
      <template v-if="userStore.currentUser">
        <div class="flex items-center gap-2 text-sm">
          <UserIcon class="h-4 w-4 text-muted-foreground" />
          <span class="text-foreground">{{ userStore.currentUser.name }}</span>
          <span class="text-xs text-muted-foreground">· {{ userStore.currentUser.department }}</span>
        </div>
        <Button variant="ghost" size="sm" @click="logout">
          <LogOut class="h-4 w-4" />
          退出
        </Button>
      </template>
      <template v-else>
        <Button variant="default" size="sm">登录</Button>
      </template>
    </div>
  </header>
</template>
