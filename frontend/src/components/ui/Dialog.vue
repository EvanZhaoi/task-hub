<script setup lang="ts">
/**
 * Dialog 组件（基于 Reka UI）
 *
 * 学习要点：
 *   1. Reka UI 是无样式可访问性原语（你写样式，它管 a11y / 焦点 / 键盘）
 *   2. v-model:open 双向绑定（DialogRoot :open + @update:open）
 *   3. as-child 模式：让 DialogTrigger 不渲染自己的按钮，而是包裹 slot 的 trigger
 *   4. Portal：把内容渲染到 body 末尾，避免被父级 z-index / overflow 影响
 */
import {
  DialogRoot,
  DialogTrigger,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
  DialogClose,
} from 'reka-ui'
import { X } from '@lucide/vue'
import { cn } from '@/lib/utils'

interface Props {
  open?: boolean
  title?: string
  description?: string
  class?: string
}

withDefaults(defineProps<Props>(), {
  open: false,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()
</script>

<template>
  <DialogRoot :open="open" @update:open="emit('update:open', $event)">
    <DialogTrigger v-if="$slots.trigger" as-child>
      <slot name="trigger" />
    </DialogTrigger>

    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-50 bg-black/40 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
      <DialogContent
        :class="cn(
          'fixed left-1/2 top-1/2 z-50 grid w-full max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 border bg-background p-6 shadow-lg duration-200 sm:rounded-lg',
          $attrs.class as string,
        )"
      >
        <div class="flex flex-col gap-2 text-center sm:text-left">
          <DialogTitle v-if="title" class="text-lg font-semibold leading-none tracking-tight">
            {{ title }}
          </DialogTitle>
          <DialogDescription v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
          </DialogDescription>
        </div>

        <slot />

        <DialogClose
          class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
        >
          <X class="h-4 w-4" />
          <span class="sr-only">关闭</span>
        </DialogClose>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
