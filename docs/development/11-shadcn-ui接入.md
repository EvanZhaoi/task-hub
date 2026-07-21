# 11-shadcn-ui接入

## 本章目标

本章解决一个前端开发中的实际问题：直接写原生 Tailwind CSS 时，按钮、卡片、输入框、状态标签会在多个页面中反复出现，代码会越来越长，也更容易出现视觉不一致。

本章接入 shadcn/ui 的组织方式，但不把 TaskHub 改成另一个前端框架。shadcn/ui 的核心思路是：把可维护的 React 组件源码放进项目中，由项目自己控制样式和行为。

## 学习目标

完成本章后，你应该理解：

- shadcn/ui 和 Tailwind CSS 的关系。
- 为什么 shadcn/ui 不是传统 npm UI 组件库。
- `components/ui` 目录负责什么。
- `cn()` 为什么几乎是 shadcn/ui 项目的基础工具。
- 为什么需要配置 `@/*` 路径别名。
- 业务页面以后如何优先使用基础 UI 组件，减少重复 Tailwind。

## 最终效果

本章完成后：

- 项目可以使用 `@/components/ui/button` 等路径导入基础组件。
- 任务大厅页面继续保持原型风格，但重复样式明显减少。
- SSO 回调页复用 Card 和 Button。
- `npm run typecheck`、`npm run build` 和 `php artisan test` 都应通过。

## 涉及文件

| 文件 | 作用 |
|---|---|
| `package.json` / `package-lock.json` | 增加 shadcn/ui 基础依赖 |
| `components.json` | shadcn/ui 项目配置，记录组件目录和别名 |
| `tsconfig.json` | 配置 TypeScript 路径别名 |
| `vite.config.ts` | 配置 Vite 路径别名 |
| `resources/js/lib/utils.ts` | 提供 `cn()` className 合并工具 |
| `resources/js/components/ui/button.tsx` | 按钮组件 |
| `resources/js/components/ui/badge.tsx` | 状态标签组件 |
| `resources/js/components/ui/card.tsx` | 卡片组件 |
| `resources/js/components/ui/input.tsx` | 输入框组件 |
| `resources/js/components/ui/native-select.tsx` | 当前阶段使用的原生下拉框组件 |
| `resources/js/Layouts/AppLayout.tsx` | 使用 Button 替换退出按钮样式 |
| `resources/js/Pages/Sso/Callback.tsx` | 使用 Card 和 Button |
| `resources/js/Pages/Tasks/Index.tsx` | 使用 UI 组件重构任务大厅 |

## 实际执行命令

命令执行目录：项目根目录。

```bash
# class-variance-authority：定义组件 variant，例如 primary / outline。
# clsx：按条件组合 className。
# tailwind-merge：合并冲突的 Tailwind class。
# @radix-ui/react-slot：支持 Button asChild，让 a 标签也能套用按钮样式。
# lucide-react：后续按钮图标优先使用的图标库。
npm install class-variance-authority clsx tailwind-merge @radix-ui/react-slot lucide-react

# Vite 配置中使用 node:path，需要 Node 类型定义。
npm install -D @types/node
```

验证安装结果：

```bash
npm run typecheck
```

如果依赖和 TypeScript 配置正确，命令应正常结束。

## 每一步操作

### 1. 配置 TypeScript 路径别名

修改 `tsconfig.json`。

```jsonc
{
    "compilerOptions": {
        // 省略其它配置。
        // @/* 统一指向 resources/js/*。
        // 这样页面里可以写 @/components/ui/button，
        // 不需要写 ../../components/ui/button。
        "paths": {
            "@/*": ["./resources/js/*"]
        },
        "types": ["vite/client"]
    }
}
```

注意：当前项目使用 TypeScript 7，`baseUrl` 已经不能使用。不要再写：

```jsonc
{
    "compilerOptions": {
        "baseUrl": "."
    }
}
```

否则会报：

```text
Option 'baseUrl' has been removed.
```

### 2. 配置 Vite 路径别名

修改 `vite.config.ts`。

```ts
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { defineConfig } from 'vite';
import { bunny } from 'laravel-vite-plugin/fonts';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    resolve: {
        alias: {
            // 让浏览器构建阶段也认识 @/xxx。
            // tsconfig 只负责 TypeScript 类型检查，Vite 负责真正打包。
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

为什么 TypeScript 和 Vite 都要配？

- `tsconfig.json` 让编辑器和 `npm run typecheck` 知道 `@/` 是什么。
- `vite.config.ts` 让 `npm run dev` 和 `npm run build` 真正能打包这些导入。

只配其中一个是不够的。

### 3. 新增 shadcn 配置文件

新增 `components.json`。

```json
{
    "$schema": "https://ui.shadcn.com/schema.json",
    "style": "new-york",
    "rsc": false,
    "tsx": true,
    "tailwind": {
        "config": "",
        "css": "resources/css/app.css",
        "baseColor": "neutral",
        "cssVariables": true,
        "prefix": ""
    },
    "aliases": {
        "components": "@/components",
        "utils": "@/lib/utils",
        "ui": "@/components/ui",
        "lib": "@/lib",
        "hooks": "@/hooks"
    },
    "iconLibrary": "lucide"
}
```

这个文件的作用类似“前端组件规范说明”。以后如果使用 shadcn CLI 添加组件，它会根据这里的目录和别名生成代码。

### 4. 新增 `cn()` 工具

新增 `resources/js/lib/utils.ts`。

```ts
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

// shadcn/ui 组件约定使用 cn() 合并 className。
// clsx 负责条件 class，tailwind-merge 负责解决 Tailwind 冲突，
// 例如 px-2 和 px-4 同时出现时保留后者。
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}
```

为什么需要它？

在业务页面中经常会出现：

```tsx
className={isActive ? 'bg-[#f5f3ff] text-[#5e6ad2]' : 'text-[#6e6e80]'}
```

这种代码本身没错，但组件越来越多后会难以维护。`cn()` 可以把“基础样式 + 条件样式 + 外部覆盖样式”统一合并。

### 5. 新增 Button

新增 `resources/js/components/ui/button.tsx`。

```tsx
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex h-10 items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors outline-none disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                primary: 'bg-[#5e6ad2] text-white hover:bg-[#4f5bd5]',
                secondary: 'bg-[#f5f3ff] text-[#5e6ad2] hover:bg-[#ede9fe]',
                outline: 'border border-[#d1d5db] bg-white text-[#4b5563] hover:border-[#9ca3af] hover:bg-[#f9fafb]',
                ghost: 'text-[#6e6e80] hover:bg-[#f3f4f6] hover:text-[#1a1a1a]',
                muted: 'border border-[#e5e7eb] bg-[#f9fafb] text-[#9ca3af]',
            },
            size: {
                default: 'px-4',
                sm: 'h-8 px-3',
                icon: 'size-8 p-0',
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'default',
        },
    },
);

type ButtonProps = ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
    };

export function Button({ asChild = false, className, size, variant, ...props }: ButtonProps) {
    // asChild 用来把按钮样式套到 a/form submit 等元素上，
    // 避免为链接按钮重复写 className。
    const Comp = asChild ? Slot : 'button';

    return <Comp className={cn(buttonVariants({ variant, size }), className)} {...props} />;
}

export { buttonVariants };
```

使用方式：

```tsx
<Button type="submit">查询</Button>

<Button asChild variant="outline">
    <a href="/tasks">重置</a>
</Button>
```

这里的重点是：业务页面不再关心按钮的边框、圆角、hover、字号，只关心“这是主按钮还是次按钮”。

### 6. 新增 Badge、Card、Input、NativeSelect

这些组件都放在 `resources/js/components/ui/` 下。

它们的作用：

- `Badge`：统一状态颜色。
- `Card`：统一卡片边框、背景和圆角。
- `Input`：统一输入框高度、边框和焦点样式。
- `NativeSelect`：先封装浏览器原生 `select`，避免当前阶段引入过重交互。

为什么暂时不用复杂 Select？

TaskHub 当前只是普通筛选表单，原生 `select` 可访问性好、表单提交简单、代码少。后续如果需要搜索、多选或异步加载，再引入 Radix Select。

### 7. 重构业务页面

重构前，任务大厅页面会直接写大量样式：

```tsx
<button
    className="h-10 rounded-md bg-[#5e6ad2] px-4 text-sm font-medium text-white hover:bg-[#4f5bd5]"
    type="submit"
>
    查询
</button>
```

重构后：

```tsx
<Button type="submit">查询</Button>
```

重构前，卡片需要重复写：

```tsx
<div className="rounded-lg border border-[#e5e7eb] bg-white p-4">
    ...
</div>
```

重构后：

```tsx
<Card>
    <CardContent>...</CardContent>
</Card>
```

这就是 shadcn/ui 对当前项目最直接的价值：不是消灭 Tailwind，而是把重复 Tailwind 收敛到可复用组件中。

## 每一步为什么这么做

shadcn/ui 的官方思路不是安装一个黑盒组件库，而是把组件源码复制到项目中维护。这样做适合 TaskHub：

- 可以保持现有原型风格。
- 可以逐步接入，不影响业务开发节奏。
- 组件源码在仓库内，团队可以直接读懂和修改。
- 不引入复杂前端框架。
- 不改变 Laravel + Inertia 单体架构。

## 如何验证

命令执行目录：项目根目录。

```bash
npm run typecheck
npm run build
php artisan test
```

再启动开发服务：

```bash
composer run dev
```

浏览器访问：

```text
http://127.0.0.1:8000/tasks
```

确认：

- 页面能正常打开。
- 查询、重置、分页按钮样式正常。
- SSO 回调页仍能显示登录状态。
- 浏览器 Console 没有 `Cannot resolve @/...` 或组件导入错误。

## 常见错误

| 错误 | 原因 | 处理方式 |
|---|---|---|
| `Cannot find module '@/components/ui/button'` | `tsconfig.json` 或 `vite.config.ts` 没配置别名 | 同时检查两个文件 |
| `Option 'baseUrl' has been removed` | TypeScript 7 不再使用 `baseUrl` | 删除 `baseUrl`，在 `paths` 中写 `./resources/js/*` |
| `Cannot find module 'node:path'` | 缺少 Node 类型定义 | 执行 `npm install -D @types/node` |
| 按钮样式没有变化 | 页面仍在用原始 `button` | 检查是否导入并使用 `Button` |
| Tailwind 样式不生效 | CSS 入口断开 | 确认 `resources/js/app.tsx` 中仍有 `import '../css/app.css'` |
| 链接按钮不能点击 | `Button asChild` 用法错误 | 使用 `<Button asChild><a href="...">...</a></Button>` |

## 本章总结

本章没有开发新业务功能，只做了前端基础设施优化：

- 引入 shadcn/ui 组件组织方式。
- 新增路径别名。
- 抽出 `cn()` 工具。
- 抽出基础 UI 组件。
- 用组件重构已有任务大厅和 SSO 回调页面。

以后写业务页面时，优先使用 `resources/js/components/ui` 中已有组件。只有确实没有合适组件时，再写页面级 Tailwind。

## 下一章预告

下一步可以开始进入发布任务功能。建议先做：

- 发布任务模态框。
- Laravel 表单请求校验。
- Task 创建事务。
- 附件只保存外部附件 ID。
- 创建后写入 TaskEvent。

发布任务会同时覆盖 React 表单、Inertia 提交、Laravel Request、Eloquent 创建和数据库事务，是第一个完整写入业务闭环。
