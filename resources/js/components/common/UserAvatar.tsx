import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';
import type { SharedPageProps } from '@/types/page';

type UserAvatarProps = {
    avatarId?: string | null;
    className?: string;
    displayName?: string | null;
    employeeNo?: string | null;
    size?: 'sm' | 'md' | 'lg';
};

const sizeClasses = {
    sm: 'size-7 text-[11px]',
    md: 'size-9 text-xs',
    lg: 'size-11 text-sm',
} as const;

/**
 * 取姓名的第一个 Unicode 字符作为头像兜底。
 *
 * 中文姓名会显示第一个汉字，英文姓名和工号会转为大写首字母。
 */
function fallbackCharacter(displayName?: string | null, employeeNo?: string | null): string {
    const source = displayName?.trim() || employeeNo?.trim() || '?';

    return Array.from(source)[0]?.toUpperCase() ?? '?';
}

/**
 * 将环境配置的 HTTP 前缀和人员接口头像 ID 安全组合。
 */
function avatarUrl(baseUrl?: string | null, avatarId?: string | null): string | null {
    const normalizedBaseUrl = baseUrl?.trim().replace(/\/+$/, '');
    const normalizedAvatarId = avatarId?.trim().replace(/^\/+/, '');

    if (!normalizedBaseUrl || !normalizedAvatarId) {
        return null;
    }

    return `${normalizedBaseUrl}/${encodeURIComponent(normalizedAvatarId)}`;
}

/**
 * 统一展示人员头像。
 *
 * 有头像 ID 时由浏览器直接懒加载图片，不经过 TaskHub PHP 转发。
 * ID 缺失或图片加载失败时显示姓名首字符，不会留下破损图片。
 */
export function UserAvatar({ avatarId, className, displayName, employeeNo, size = 'md' }: UserAvatarProps) {
    const { app } = usePage<SharedPageProps>().props;
    const imageUrl = avatarUrl(app?.avatarBaseUrl, avatarId);
    const [imageFailed, setImageFailed] = useState(false);
    const label = displayName?.trim() || employeeNo?.trim() || '未知用户';

    // 组件被复用于不同人员时重置失败状态，允许新 URL 正常尝试加载。
    useEffect(() => {
        setImageFailed(false);
    }, [imageUrl]);

    return (
        <span
            aria-label={`${label}的头像`}
            className={cn(
                'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#eeedff] font-semibold text-[#4f46a5] ring-1 ring-[#dedcf7]',
                sizeClasses[size],
                className,
            )}
            title={label}
        >
            {imageUrl && !imageFailed ? (
                <img
                    alt={`${label}的头像`}
                    className="size-full object-cover"
                    decoding="async"
                    loading="lazy"
                    onError={() => setImageFailed(true)}
                    src={imageUrl}
                />
            ) : (
                <span aria-hidden="true">{fallbackCharacter(displayName, employeeNo)}</span>
            )}
        </span>
    );
}
