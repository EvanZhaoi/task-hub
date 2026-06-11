<script setup lang="ts">
/**
 * ProfileView
 * 演示：Tab 切换（用 Button 变体实现）
 */
import { ref } from 'vue'
import { useUserStore } from '@/stores/user'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'

const userStore = useUserStore()
const activeTab = ref<'published' | 'bidded' | 'assigned'>('published')
</script>

<template>
  <div class="mx-auto max-w-5xl px-6 py-8">
    <div v-if="userStore.currentUser" class="mb-6 flex items-center gap-4">
      <div
        class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-primary to-purple-500 text-sm font-medium text-white"
      >
        {{ userStore.currentUser.name.charAt(0) }}
      </div>
      <div>
        <h1 class="text-2xl font-semibold text-foreground">{{ userStore.currentUser.name }}</h1>
        <p class="mt-1 text-sm text-muted-foreground">{{ userStore.currentUser.department }}</p>
      </div>
    </div>

    <!-- Tab 切换：用 Button 的 variant 实现 -->
    <div class="mb-6 flex gap-1 border-b border-border">
      <Button
        v-for="tab in [
          { key: 'published', label: '我发布的', count: 0 },
          { key: 'bidded',    label: '我投标的', count: 0 },
          { key: 'assigned',  label: '我接的',   count: 0 },
        ]"
        :key="tab.key"
        :variant="activeTab === tab.key ? 'default' : 'ghost'"
        size="sm"
        @click="activeTab = tab.key as 'published' | 'bidded' | 'assigned'"
      >
        {{ tab.label }} ({{ tab.count }})
      </Button>
    </div>

    <Card class="py-12 text-center text-muted-foreground">
      <p>个人主页</p>
      <p class="mt-2 text-xs">Phase 1 待实现：3 个 Tab 的任务列表 + 我的任务时间线（甘特图）</p>
    </Card>
  </div>
</template>
