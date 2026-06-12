import { cn } from '@/lib/utils'
import type { User, UserRole } from '@/api/types'

interface UserAvatarProps {
  user: Pick<User, 'name' | 'role'> | { name: string; role: UserRole }
  size?: 'xs' | 'sm' | 'md' | 'lg'
  className?: string
}

const SIZE_CLASSES = {
  xs: 'h-5 w-5 text-[10px]',
  sm: 'h-7 w-7 text-xs',
  md: 'h-8 w-8 text-sm',
  lg: 'h-10 w-10 text-base',
}

/** 渐变色头像：根据角色配色，首字母居中 */
export function UserAvatar({ user, size = 'sm', className }: UserAvatarProps) {
  const bg =
    user.role === 'publisher'
      ? 'linear-gradient(135deg, #f97316 0%, #fb923c 100%)'
      : user.role === 'boss'
      ? 'linear-gradient(135deg, #1a1a1a 0%, #4b5563 100%)'
      : 'linear-gradient(135deg, #5e6ad2 0%, #8b5cf6 100%)'

  return (
    <div
      className={cn(
        'flex shrink-0 items-center justify-center rounded-full font-medium text-white shadow-sm',
        SIZE_CLASSES[size],
        className,
      )}
      style={{ background: bg }}
      title={user.name}
    >
      {user.name.charAt(0)}
    </div>
  )
}