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
- shadcn CLI 生成的默认组件样式会被调整为 TaskHub 当前原型风格，不直接照搬默认主题。
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

说明：`components.json`、`package.json` 和 `tsconfig.json` 是 JSON 文件，标准 JSON 语法不能写注释。因此这些文件的字段解释放在本文档中；PHP、TS、TSX、Blade、CSS 等支持注释的项目代码会在代码内补充中文注释。

## 实际执行命令

命令执行目录：项目根目录。

```bash
# 新版 shadcn 推荐先用 init 初始化已有项目。
# 这个命令会根据提示生成 components.json、安装基础依赖、创建 cn() 工具。
npx shadcn@latest init

# 再用 add 命令生成需要的基础组件。
# 能用命令生成的组件，不手写；生成后只做 TaskHub 风格所需的小范围调整。
npx shadcn@latest add button badge card input

# Vite 配置中使用 node:path，需要 Node 类型定义；如果项目已安装，可以跳过。
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

### 3. 生成 shadcn 配置文件

新版 shadcn 不需要优先手写 `components.json`。执行：

```bash
# 在已有项目中初始化 shadcn/ui。
# 这一步会生成 components.json，并安装 class-variance-authority、clsx、tailwind-merge、lucide-react 等依赖。
npx shadcn@latest init
```

命令执行时按 TaskHub 当前结构选择：

- TypeScript：是。
- style：建议选择 `new-york`。
- base color：建议选择 `neutral`。
- CSS 文件：`resources/css/app.css`。
- components alias：`@/components`。
- utils alias：`@/lib/utils`。
- React Server Components：否。TaskHub 是 Laravel + Inertia，不使用 RSC。

执行后会生成或更新 `components.json`。当前仓库最终配置如下：

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

这个文件的作用类似“前端组件规范说明”。以后使用 `npx shadcn@latest add xxx` 添加组件时，CLI 会根据这里的目录和别名生成代码。

如果你使用的是新版 shadcn，并且 `npx shadcn@latest init` 已经生成了正确的 `components.json`，就不需要手动创建它。只有在以下情况才手动调整：

- CLI 没识别 Laravel 项目的 `resources/css/app.css`。
- CLI 生成的 alias 不是 `@/components`、`@/lib/utils`。
- `tailwind.css` 指向了不存在的 CSS 文件。
- Tailwind v4 项目中 `tailwind.config` 不应指向旧的 `tailwind.config.js`。

字段解释：

- `$schema`：让编辑器识别 shadcn 配置结构。
- `style`：组件生成风格，当前使用 `new-york`。
- `rsc`：React Server Components 开关；TaskHub 是 Laravel + Inertia，不使用 RSC。
- `tsx`：表示生成 TypeScript React 组件。
- `tailwind.css`：告诉 shadcn 当前 Tailwind 入口文件是 `resources/css/app.css`。
- `aliases`：告诉 shadcn 组件、工具函数、hook 应该生成到哪里。
- `iconLibrary`：后续需要图标时优先使用 `lucide`。

### 4. 生成 `cn()` 工具

新版 shadcn 的 `init` 命令会自动创建 `resources/js/lib/utils.ts`，通常不需要手写。

当前仓库中的最终内容如下，主要用于理解它的作用：

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

### 5. 使用命令生成 Button

不要优先手写 `resources/js/components/ui/button.tsx`。使用命令生成：

```bash
# 生成 Button 组件源码到 resources/js/components/ui/button.tsx。
# CLI 会根据 components.json 中的 alias 自动处理 import 路径。
npx shadcn@latest add button
```

生成后必须根据 TaskHub 原型图做小范围样式调整，例如主色、边框色、字号和圆角。不要直接保持 shadcn 默认视觉，否则任务大厅会和现有原型风格不一致。

当前仓库最终使用的 Button 结构如下。它和 shadcn 生成代码的思路一致：用 `cva` 管理 variant，用 `Slot` 支持 `asChild`。

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

### 6. 生成后同步 TaskHub 视觉风格

CLI 生成组件只是第一步。shadcn 的默认样式是通用后台风格，不等于 TaskHub 最终设计稿。TaskHub 当前已经有原型图和任务大厅视觉，因此生成组件后要把“基础组件默认样式”调整为项目风格。

需要同步的样式：

- 主色：继续使用当前任务大厅主色 `#5e6ad2`，hover 使用 `#4f5bd5`。
- 页面背景：继续使用浅灰工作台背景 `#f7f7f8`。
- 卡片：保持白底、浅边框 `#e5e7eb`、8px 左右圆角，不做夸张阴影。
- 表单控件：高度统一 `h-10`，边框使用 `#d1d5db`，focus 使用低透明品牌色 ring。
- 状态色：招标中、待选标、进行中、完成、流标、取消继续使用当前 Badge 映射，不直接使用 shadcn 默认 destructive/secondary 文案。
- 字体层级：任务大厅是业务工作台，不使用过大的营销式标题。

不建议改的内容：

- 不为了“看起来像 shadcn 官网”改掉原型图风格。
- 不在每个页面重复写按钮和卡片 className。
- 不把业务状态颜色散落在多个页面里；先集中在 Badge variant 或模块展示配置中。

当前仓库已经做过的样式同步示例：

```tsx
const buttonVariants = cva(
    // 基础按钮结构仍沿用 shadcn/cva 思路。
    'inline-flex h-10 items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors outline-none disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                // 主按钮颜色改成 TaskHub 原型主色，而不是照搬 shadcn 默认 primary。
                primary: 'bg-[#5e6ad2] text-white hover:bg-[#4f5bd5]',
                // outline 用于退出、重置、分页等弱动作，保持业务系统克制风格。
                outline: 'border border-[#d1d5db] bg-white text-[#4b5563] hover:border-[#9ca3af] hover:bg-[#f9fafb]',
            },
        },
    },
);
```

后续新增组件也按同样方式处理：先用 CLI 生成，再调整到 TaskHub 原型风格。

### 7. 使用命令生成 Badge、Card、Input

继续使用 CLI：

```bash
# 一次生成多个组件。
# 能用 shadcn 命令生成的组件，后续都按这个方式处理。
npx shadcn@latest add badge card input
```

生成的组件都放在 `resources/js/components/ui/` 下。

它们的作用：

- `Badge`：统一状态颜色。
- `Card`：统一卡片边框、背景和圆角。
- `Input`：统一输入框高度、边框和焦点样式。
- `NativeSelect`：先封装浏览器原生 `select`，避免当前阶段引入过重交互。

`NativeSelect` 不是 shadcn 官方组件，而是 TaskHub 当前阶段的轻量封装。原因是任务大厅筛选只需要普通 HTML 表单提交，原生 `select` 更简单，也更适合 GET 表单。

为什么暂时不用复杂 Select？

TaskHub 当前只是普通筛选表单，原生 `select` 可访问性好、表单提交简单、代码少。后续如果需要搜索、多选或异步加载，再引入 Radix Select。

### 8. 重构业务页面

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
| `components.json` 已存在，CLI 提示覆盖 | 项目已经初始化过 shadcn | 不要盲目覆盖，先对比当前 alias 和 css 配置 |
| CLI 生成的组件样式和原型图不一致 | shadcn 默认样式不是 TaskHub 最终视觉 | 保留组件结构，只按原型调整颜色、间距、圆角 |
| 按钮样式没有变化 | 页面仍在用原始 `button` | 检查是否导入并使用 `Button` |
| Tailwind 样式不生效 | CSS 入口断开 | 确认 `resources/js/app.tsx` 中仍有 `import '../css/app.css'` |
| 链接按钮不能点击 | `Button asChild` 用法错误 | 使用 `<Button asChild><a href="...">...</a></Button>` |

## 本章总结

本章没有开发新业务功能，只做了前端基础设施优化：

- 引入 shadcn/ui 组件组织方式。
- 新增路径别名。
- 使用 `npx shadcn@latest init` 生成基础配置和 `cn()` 工具。
- 使用 `npx shadcn@latest add ...` 生成基础 UI 组件。
- 将 CLI 生成的默认组件样式调整为 TaskHub 原型风格。
- 用组件重构已有任务大厅和 SSO 回调页面。

以后写业务页面时，优先使用 `resources/js/components/ui` 中已有组件。只有确实没有合适组件时，再写页面级 Tailwind。

后续开发约定：

- Laravel、React、Inertia、shadcn 能用官方命令生成的文件，优先使用命令生成。
- 命令生成后再按 TaskHub 数据库、原型图和业务规则调整。
- 不为“学习展示”手写大量无意义样板代码。
- 文档要写清楚命令、生成了哪些文件、哪些地方是生成后手动改的。

## 下一章预告

下一步可以开始进入发布任务功能。建议先做：

- 发布任务模态框。
- Laravel 表单请求校验。
- Task 创建事务。
- 附件只保存外部附件 ID。
- 创建后写入 TaskEvent。

发布任务会同时覆盖 React 表单、Inertia 提交、Laravel Request、Eloquent 创建和数据库事务，是第一个完整写入业务闭环。
