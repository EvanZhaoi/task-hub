<script setup lang="ts">
/**
 * HomeView
 * 演示：Card 组件使用（带 Header / Title / Content）
 */
import Card from '@/components/ui/Card.vue'
</script>

<template>
  <div class="mx-auto max-w-7xl px-6 py-8">
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-foreground">任务大厅</h1>
        <p class="mt-1 text-sm text-muted-foreground">所有公开任务，可投标</p>
      </div>
    </div>

    <Card class="py-12 text-center text-muted-foreground">
      <p>任务列表</p>
      <p class="mt-2 text-xs">Phase 1 待实现：搜索 / 筛选 / 分页 / TaskCard 组件</p>
    </Card>
  </div>
</template>
