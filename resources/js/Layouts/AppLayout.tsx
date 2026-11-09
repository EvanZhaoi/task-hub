import { Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { csrfToken } from '@/utils/csrf';
import type { SharedPageProps } from '@/types/page';

type AppLayoutProps = {
    // 我的任务使用下拉菜单承载三个独立页面，不在顶部横向占用三个导航位置。
    activeNav: 'tasks' | 'my-tasks';
    // actions 用于放页面级操作按钮，例如任务大厅的“发布任务”。
    actions?: ReactNode;
    // children 是具体业务页面内容，布局只负责外壳、导航和标题区域。
    children: ReactNode;
    subtitle?: string;
    title: string;
};

const myTaskItems = [
    { label: '我发布的', href: '/my-tasks/published' },
    { label: '我的投标', href: '/my-tasks/bids' },
    { label: '执行任务', href: '/my-tasks/assignments' },
] as const;

export default function AppLayout({ actions, activeNav, children, subtitle, title }: AppLayoutProps) {
    // auth 来自 Inertia 全局共享 props，后端来源是 Laravel Session。
    const page = usePage<SharedPageProps>();
    const { auth } = page.props;
    const user = auth?.user;
    const roles = auth?.roles ?? [];
    // 优先展示姓名；没有姓名时展示工号；都没有说明 Session 中没有可展示用户信息。
    const userLabel = user?.displayName ?? user?.employeeNo ?? '未识别用户';
    const departmentLabel = user?.departmentName ?? '未同步部门';
    // 只取 pathname 判断下拉菜单当前项，查询条件变化不影响导航选中状态。
    const currentPath = page.url.split('?')[0];
    const [isMyTasksOpen, setIsMyTasksOpen] = useState(false);
    const myTasksMenuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        /**
         * 点击导航菜单之外的区域时关闭菜单，避免菜单一直覆盖页面内容。
         */
        function closeOnOutsideClick(event: MouseEvent): void {
            if (!myTasksMenuRef.current?.contains(event.target as Node)) {
                setIsMyTasksOpen(false);
            }
        }

        /**
         * 键盘用户按 Escape 时关闭菜单，并保持浏览器原有焦点顺序。
         */
        function closeOnEscape(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                setIsMyTasksOpen(false);
            }
        }

        document.addEventListener('mousedown', closeOnOutsideClick);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', closeOnOutsideClick);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, []);

    return (
        <main className="min-h-screen bg-[#f7f7f8] text-[#1a1a1a]">
            <header className="sticky top-0 z-20 border-b border-[#e5e7eb] bg-white/95 px-6 py-3 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-[#5e6ad2] text-sm font-bold text-white">
                            T
                        </div>
                        <div className="hidden sm:block">
                            <div className="text-sm font-semibold leading-5">TaskHub</div>
                            <div className="text-xs text-[#6e6e80]">任务协作与内部悬赏</div>
                        </div>
                    </div>

                    <nav className="flex items-center gap-1 text-sm" aria-label="主导航">
                        <Link
                            className={cn(
                                'rounded-md px-3 py-1.5 transition-colors',
                                activeNav === 'tasks'
                                    ? 'bg-[#f5f3ff] font-medium text-[#5e6ad2]'
                                    : 'text-[#6e6e80] hover:bg-[#f3f4f6] hover:text-[#1a1a1a]',
                            )}
                            href="/tasks"
                        >
                            任务大厅
                        </Link>

                        <div className="relative" ref={myTasksMenuRef}>
                            <Button
                                aria-expanded={isMyTasksOpen}
                                aria-haspopup="menu"
                                className={cn(
                                    'h-8 gap-1 px-3',
                                    activeNav === 'my-tasks' && 'bg-[#f5f3ff] text-[#5e6ad2]',
                                )}
                                onClick={() => setIsMyTasksOpen((isOpen) => !isOpen)}
                                type="button"
                                variant="ghost"
                            >
                                我的任务
                                <ChevronDown className={cn('size-4 transition-transform', isMyTasksOpen && 'rotate-180')} />
                            </Button>

                            {isMyTasksOpen ? (
                                <div
                                    aria-label="我的任务导航"
                                    className="absolute left-1/2 top-full z-50 mt-1 w-36 -translate-x-1/2 rounded-md border border-[#e5e7eb] bg-white p-1 shadow-lg"
                                    role="menu"
                                >
                                {myTaskItems.map((item) => (
                                    <Link
                                        className={cn(
                                            'block w-full rounded-sm px-3 py-2 text-sm text-[#374151] hover:bg-[#f3f4f6]',
                                            currentPath === item.href && 'bg-[#f5f3ff] font-medium text-[#5e6ad2]',
                                        )}
                                        href={item.href}
                                        key={item.href}
                                        onClick={() => setIsMyTasksOpen(false)}
                                        role="menuitem"
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                                </div>
                            ) : null}
                        </div>
                    </nav>

                    <div className="flex items-center gap-3">
                        <div className="hidden text-right md:block">
                            <div className="text-sm font-medium">{userLabel}</div>
                            <div className="text-xs text-[#6e6e80]">
                                {departmentLabel}
                                {roles.length > 0 ? ` · ${roles.join(' / ')}` : ''}
                            </div>
                        </div>

                        <form action="/logout" method="POST">
                            {/* 原生表单退出会触发浏览器顶层导航，避免 Inertia Ajax 跟随外部 SSO 302 引发 CORS。 */}
                            <input name="_token" type="hidden" value={csrfToken()} />

                            <Button size="sm" type="submit" variant="outline">
                                退出
                            </Button>
                        </form>
                    </div>
                </div>
            </header>

            <section className="mx-auto max-w-7xl px-6 py-8">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        {/* 页面标题由业务页传入，保持所有业务页标题层级一致。 */}
                        <h1 className="m-0 text-2xl font-bold tracking-normal">{title}</h1>
                        {subtitle ? <p className="mt-1 text-sm text-[#6e6e80]">{subtitle}</p> : null}
                    </div>
                    {actions ? <div className="flex items-center gap-3">{actions}</div> : null}
                </div>

                {/* 具体业务页面内容渲染在这里，例如任务大厅列表。 */}
                {children}
            </section>
        </main>
    );
}
