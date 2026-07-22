import type { ReactNode } from 'react';
import { usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { csrfToken } from '@/utils/csrf';
import type { SharedPageProps } from '@/types/page';

type AppLayoutProps = {
    // 当前只有任务大厅一个一级导航，类型先收窄，后续新增模块时再扩展。
    activeNav: 'tasks';
    // actions 用于放页面级操作按钮，例如任务大厅的“发布任务”。
    actions?: ReactNode;
    // children 是具体业务页面内容，布局只负责外壳、导航和标题区域。
    children: ReactNode;
    subtitle?: string;
    title: string;
};

const navItems = [
    // 这里只放已经落地的一级入口；发布/修改任务后续在任务大厅中用模态框承载。
    { key: 'tasks', label: '任务大厅', href: '/tasks' },
] as const;

export default function AppLayout({ actions, activeNav, children, subtitle, title }: AppLayoutProps) {
    // auth 来自 Inertia 全局共享 props，后端来源是 Laravel Session。
    const { auth } = usePage<SharedPageProps>().props;
    const user = auth?.user;
    const roles = auth?.roles ?? [];
    // 优先展示姓名；没有姓名时展示工号；都没有说明 Session 中没有可展示用户信息。
    const userLabel = user?.displayName ?? user?.employeeNo ?? '未识别用户';
    const departmentLabel = user?.departmentName ?? '未同步部门';

    return (
        <main className="min-h-screen bg-[#f7f7f8] text-[#1a1a1a]">
            <header className="sticky top-0 z-20 border-b border-[#e5e7eb] bg-white/95 px-6 py-3 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-[#5e6ad2] text-sm font-bold text-white">
                            T
                        </div>
                        <div>
                            <div className="text-sm font-semibold leading-5">TaskHub</div>
                            <div className="text-xs text-[#6e6e80]">任务协作与内部悬赏</div>
                        </div>
                    </div>

                    <nav className="flex items-center gap-1 text-sm">
                        {navItems.map((item) => {
                            // activeNav 由页面传入，布局只负责把当前入口标成选中态。
                            const isActive = item.key === activeNav;

                            return (
                                <a
                                    className={
                                        isActive
                                            ? 'rounded-md bg-[#f5f3ff] px-3 py-1.5 font-medium text-[#5e6ad2]'
                                            : 'rounded-md px-3 py-1.5 text-[#6e6e80] hover:bg-[#f3f4f6] hover:text-[#1a1a1a]'
                                    }
                                    href={item.href}
                                    key={item.key}
                                >
                                    {item.label}
                                </a>
                            );
                        })}
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
