<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useUserStore } from '@/stores/user'

const route = useRoute()
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
  window.location.href = '/'
}
</script>

<template>
  <header
    class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between sticky top-0 z-40"
  >
    <!-- 左：Logo + 导航 -->
    <div class="flex items-center gap-8">
      <router-link to="/" class="flex items-center gap-2 no-underline">
        <div
          class="w-7 h-7 rounded-md flex items-center justify-center font-bold text-white text-sm"
          style="background: #5e6ad2"
        >
          T
        </div>
        <h1 class="text-base font-semibold text-gray-900">TaskHub</h1>
      </router-link>

      <nav class="flex gap-1 text-sm">
        <router-link
          v-for="item in navItems"
          :key="item.name"
          :to="item.path"
          class="nav-link"
          :class="{ 'nav-link-active': isActive(item.path) }"
        >
          {{ item.label }}
        </router-link>
      </nav>
    </div>

    <!-- 右：用户区 -->
    <div class="flex items-center gap-3">
      <template v-if="userStore.currentUser">
        <div class="flex items-center gap-2 text-sm">
          <span
            class="avatar"
            :class="{
              'avatar-publisher': userStore.isPublisher,
              'avatar-boss': userStore.isBoss,
            }"
            >{{ userStore.currentUser.name.charAt(0) }}</span
          >
          <span class="text-gray-700">{{ userStore.currentUser.name }}</span>
          <span class="text-gray-400 text-xs">{{ userStore.currentUser.department }}</span>
        </div>
        <button @click="logout" class="btn-ghost text-xs">退出</button>
      </template>
      <template v-else>
        <button class="btn-primary text-sm">登录</button>
      </template>
    </div>
  </header>
</template>

<style scoped>
.nav-link {
  @apply px-3 py-1.5 rounded-md text-gray-600 no-underline transition-colors;
}
.nav-link:hover {
  @apply text-gray-900 bg-gray-50;
}
.nav-link-active {
  @apply text-brand-500 bg-brand-50 font-medium;
}
</style>
