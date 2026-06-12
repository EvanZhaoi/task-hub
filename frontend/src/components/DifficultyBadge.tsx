import { difficultyLabel } from '@/utils/format'
import type { Difficulty } from '@/api/types'

interface DifficultyBadgeProps {
  difficulty: Difficulty
  size?: 'sm' | 'md'
}

/**
 * 任务难度徽章
 * - EASY 简单 → 绿
 * - NORMAL 普通 → 灰
 * - HARD 困难 → 红
 */
export function DifficultyBadge({ difficulty, size = 'sm' }: DifficultyBadgeProps) {
  const sizeCls = size === 'md' ? 'text-sm px-2.5 py-0.5' : ''
  return (
    <span className={`badge badge-${difficulty.toLowerCase()} ${sizeCls}`}>
      {difficultyLabel[difficulty]}
    </span>
  )
}