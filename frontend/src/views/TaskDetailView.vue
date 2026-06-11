<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()
const taskId = route.params.id as string
</script>

<template>
  <div class="max-w-5xl mx-auto px-6 py-8">
    <router-link to="/" class="text-sm text-gray-500 hover:text-gray-900">
      ← 返回任务大厅
    </router-link>
    <h1 class="text-3xl font-bold text-gray-900 mt-4">任务详情</h1>
    <p class="text-sm text-gray-500 mt-2">任务 ID：{{ taskId }}</p>

    <div class="card mt-6 text-center py-12 text-gray-500">
      <p>任务详情</p>
      <p class="text-xs mt-2">Phase 1 待实现：描述渲染 / 投标列表 / 选标 / 完成 / 延期 / 附件</p>
    </div>
  </div>
</template>
