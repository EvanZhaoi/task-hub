<script setup lang="ts">
/**
 * Button 组件（shadcn-vue 风格）
 *
 * 学习要点：
 *   1. cva (class-variance-authority) 定义变体：variant + size 组合
 *   2. cn() 工具：合并默认变体类 + 外部传入的类（外部类优先级最高）
 *   3. withDefaults + 泛型 Props：类型安全 + 默认值
 *   4. <slot />：Vue 的插槽，让父组件传任意内容
 */
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/utils'

// 变体定义：varian t (外观) + size (大小)
const buttonVariants = cva(
  // 基础类（所有变体共享）
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground hover:bg-primary/90',
        destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
        outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        ghost: 'hover:bg-accent hover:text-accent-foreground',
        link: 'text-primary underline-offset-4 hover:underline',
      },
      size: {
        default: 'h-10 px-4 py-2',
        sm: 'h-9 rounded-md px-3',
        lg: 'h-11 rounded-md px-8',
        icon: 'h-10 w-10',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
)

type ButtonVariants = VariantProps<typeof buttonVariants>

interface Props {
  variant?: ButtonVariants['variant']
  size?: ButtonVariants['size']
  class?: string
  disabled?: boolean
  type?: 'button' | 'submit' | 'reset'
}

withDefaults(defineProps<Props>(), {
  variant: 'default',
  size: 'default',
  disabled: false,
  type: 'button',
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    :class="cn(buttonVariants({ variant, size }), $attrs.class as string)"
  >
    <slot />
  </button>
</template>
