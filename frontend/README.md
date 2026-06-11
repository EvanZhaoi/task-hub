# TaskHub Frontend (React 版)

> React 19 + TypeScript + Vite + React Router 7 + Zustand + shadcn/ui

> 📦 旧版 Vue 3 架子在 `../frontend-vue-archive/`（仅作对照，不维护）

## 🚀 快速开始

```bash
npm install
npm run dev          # http://localhost:5173
npm run type-check    # tsc --noEmit
npm run build        # 产物在 dist/
npm run preview      # 预览生产包
```

## 📦 依赖（全部最新稳定）

| 包 | 版本 | 用途 |
|---|---|---|
| `react` / `react-dom` | 19.x | 框架 |
| `react-router` | 7.x | 路由（数据路由模式） |
| `zustand` | 5.x | 状态管理 |
| `axios` | 1.7.x | HTTP 客户端 |
| `@radix-ui/react-*` | 1.1.x | 无样式可访问性原语 |
| `class-variance-authority` | 0.7.x | 变体系统 |
| `clsx` + `tailwind-merge` | latest | className 工具 |
| `lucide-react` | 0.469.x | 图标 |
| `tailwindcss` | 3.4.x | CSS 框架 |
| `vite` | 6.x | 构建工具 |
| `typescript` | 5.7.x | 类型系统 |

## 📁 目录结构

```
frontend/
├── public/                  # 静态资源
├── src/
│   ├── api/                 # API 客户端（Axios）
│   │   ├── client.ts        # Axios + 拦截器
│   │   ├── types.ts         # 共享类型
│   │   ├── task.ts / bid.ts / user.ts
│   ├── stores/              # Zustand stores
│   │   ├── user.ts          # 用户（含 persist 中间件）
│   │   ├── tasks.ts
│   │   └── bids.ts
│   ├── views/               # 页面级组件
│   │   ├── HomeView.tsx
│   │   ├── TaskDetailView.tsx
│   │   ├── CreateTaskView.tsx
│   │   ├── ProfileView.tsx
│   │   └── BossGanttView.tsx
│   ├── components/
│   │   ├── AppHeader.tsx
│   │   └── ui/              # shadcn/ui 组件
│   │       ├── button.tsx
│   │       ├── card.tsx
│   │       ├── input.tsx
│   │       └── dialog.tsx
│   ├── lib/
│   │   └── utils.ts         # cn() 工具
│   ├── router/
│   │   └── index.tsx        # React Router 7 配置
│   ├── utils/
│   │   └── format.ts
│   ├── styles/
│   │   └── globals.css
│   ├── main.tsx
│   ├── App.tsx              (Layout 在 router 内)
│   └── vite-env.d.ts
├── index.html
├── vite.config.ts
├── tailwind.config.js
├── postcss.config.js
├── tsconfig.json
├── components.json          # shadcn/ui 配置
├── env.d.ts
└── package.json
```

## 🔄 Vue → React 关键差异速查

| 概念 | Vue | React |
|---|---|---|
| 组件文件 | `.vue` SFC | `.tsx` 函数组件 |
| 模板 | `<template>` | `return <JSX>` |
| 响应式 | `ref()` | `useState()` |
| 计算属性 | `computed()` | `useMemo()` 或直接算 |
| 副作用 | `watch()` | `useEffect()` |
| Props | `defineProps<{...}>()` | `interface Props { ... }` + 函数参数 |
| 事件 | `defineEmits<{...}>()` + `emit('xxx')` | `props.onXxx` 回调 |
| 双向绑定 | `v-model` | `value` + `onChange` 显式 |
| 插槽 | `<slot />` | `children` prop |
| 路由链接 | `<router-link to="/">` | `<Link to="/">` |
| 路由出口 | `<router-view />` | `<Outlet />` |
| 状态管理 | Pinia (ref + computed) | Zustand (state + actions) |

## 🛣 路由

| Path | Component |
|---|---|
| `/` | HomeView |
| `/task/:id` | TaskDetailView |
| `/create` | CreateTaskView |
| `/profile` | ProfileView |
| `/boss` | BossGanttView |

## 🚧 当前状态

✅ **架构就位**：
- Vite + TS + Tailwind 构建配置
- React Router 7 数据路由 + 路由级 lazy
- Zustand 3 个 store（带 persist 中间件）
- Axios + 拦截器
- shadcn/ui 4 个组件（Button / Card / Input / Dialog）
- AppHeader 完整可用

⏳ **待 Phase 1 实现**：
- 5 个 view 的真实内容
- TipTap 富文本集成
- 通用 GanttChart 组件
- 业务组件（TaskCard / BidListItem / AttachmentItem 等）

## 📚 相关文档

- [需求文档 v0.4](../docs/需求文档.md)
- [技术选型 v0.5](../docs/技术选型.md)（v0.5.4 已含 React 切换）
- [模块与数据库设计 v0.6](../docs/模块与数据库设计.md)
- [原型](../prototype/)（Vanilla HTML + Alpine.js，保留作为设计参照）
- [Vue 版架子在 ../frontend-vue-archive/](../frontend-vue-archive/)
