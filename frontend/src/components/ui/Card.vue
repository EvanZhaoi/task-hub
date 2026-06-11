<script setup lang="ts">
/**
 * Card 组件
 * 用法：<Card><CardHeader><CardTitle>标题</CardTitle></CardHeader><CardContent>...</CardContent></Card>
 */
import { cn } from '@/lib/utils'

interface Props {
  class?: string
}
defineProps<Props>()
</script>

<template>
  <div :class="cn('rounded-lg border bg-card text-card-foreground shadow-sm', $attrs.class as string)">
    <slot />
  </div>
</template>
