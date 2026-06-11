<script setup lang="ts">
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()
</script>

<template>
  <div class="max-w-5xl mx-auto px-6 py-8">
    <div v-if="userStore.currentUser" class="flex items-center gap-4 mb-6">
      <span
        class="avatar avatar-lg"
        :class="{
          'avatar-publisher': userStore.isPublisher,
          'avatar-boss': userStore.isBoss,
        }"
        style="width: 40px; height: 40px; font-size: 14px"
      >
        {{ userStore.currentUser.name.charAt(0) }}
      </span>
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">{{ userStore.currentUser.name }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ userStore.currentUser.department }}</p>
      </div>
    </div>

    <!-- Tab 切换：发布 / 投标 / 接单 -->
    <div class="flex gap-1 border-b border-gray-200 mb-6">
      <button class="tab tab-active">我发布的 (0)</button>
      <button class="tab">我投标的 (0)</button>
      <button class="tab">我接的 (0)</button>
    </div>

    <div class="card text-center py-12 text-gray-500">
      <p>个人主页</p>
      <p class="text-xs mt-2">Phase 1 待实现：3 个 Tab 的任务列表 + 我的任务时间线（甘特图）</p>
    </div>
  </div>
</template>

<style scoped>
.tab {
  @apply px-3 py-2 text-sm text-gray-600 border-b-2 border-transparent cursor-pointer transition-colors;
}
.tab:hover {
  @apply text-gray-900;
}
.tab-active {
  @apply text-brand-500 border-brand-500 font-medium;
}
</style>
