import { complexityLabel } from '@/utils/format'
import type { Complexity } from '@/api/types'

interface ComplexityBadgeProps {
  complexity: Complexity
  size?: 'sm' | 'md'
}

const COMPLEXITY_BG: Record<Complexity, string> = {
  LOW:    'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
  MEDIUM: 'bg-slate-50 text-slate-700 ring-1 ring-inset ring-slate-500/20',
  HIGH:   'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
}

/**
 * 任务复杂度徽章
 * - LOW 简单 → 绿（绿调）
 * - MEDIUM 中等复杂 → 灰（中性）
 * - HIGH 高度复杂 → 红（警示）
 */
export function ComplexityBadge({ complexity, size = 'sm' }: ComplexityBadgeProps) {
  const sizeCls = size === 'md' ? 'text-sm px-2.5 py-0.5 font-medium' : 'font-medium'
  return (
    <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs ${COMPLEXITY_BG[complexity]} ${sizeCls}`}>
      <span className="inline-block h-1 w-1 rounded-full bg-current opacity-70" />
      {complexityLabel[complexity]}
    </span>
  )
}