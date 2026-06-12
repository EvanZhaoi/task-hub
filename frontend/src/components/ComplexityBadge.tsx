import { complexityLabel } from '@/utils/format'
import type { Complexity } from '@/api/types'

interface ComplexityBadgeProps {
  complexity: Complexity
  size?: 'sm' | 'md'
}

/**
 * 任务复杂度徽章
 * - LOW 简单 → 绿
 * - MEDIUM 中等复杂 → 灰
 * - HIGH 高度复杂 → 红
 */
export function ComplexityBadge({ complexity, size = 'sm' }: ComplexityBadgeProps) {
  const sizeCls = size === 'md' ? 'text-sm px-2.5 py-0.5' : ''
  return (
    <span className={`badge badge-${complexity.toLowerCase()} ${sizeCls}`}>
      {complexityLabel[complexity]}
    </span>
  )
}