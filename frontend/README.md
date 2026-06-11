# TaskHub Frontend

> Vue 3 + TypeScript + Vite + Pinia + Tailwind + TipTap

## 🚀 快速开始

```bash
# 安装依赖（推荐 pnpm，npm 也行）
npm install

# 启动开发服务器
npm run dev
# 访问 http://localhost:5173

# 类型检查
npm run type-check

# 构建生产包
npm run build

# 预览生产包
npm run preview
```

## 📦 依赖（全部最新稳定版）

| 包 | 版本 | 用途 |
|---|---|---|
| `vue` | 3.5.x | 框架 |
| `vue-router` | 4.5.x | 路由（hash 模式） |
| `pinia` | 2.3.x | 状态管理 |
| `axios` | 1.7.x | HTTP 客户端 |
| `@tiptap/vue-3` + 扩展 | 2.10.x | 富文本编辑器 |
| `@vueuse/core` | 12.5.x | Vue 组合式工具集 |
| `dayjs` | 1.11.x | 日期处理 |
| `vite` | 6.0.x | 构建工具 |
| `typescript` | 5.7.x | 类型系统 |
| `tailwindcss` | 3.4.x | CSS 框架 |

## 📁 目录结构

```
frontend/
├── public/                  # 静态资源
├── src/
│   ├── api/                 # API 客户端
│   │   ├── client.ts        # Axios 实例 + 拦截器
│   │   ├── types.ts         # 共享类型
│   │   ├── task.ts          # 任务 API
│   │   ├── bid.ts           # 投标 API
│   │   ├── user.ts          # 用户 API
│   │   └── attachment.ts    # 附件 API
│   ├── stores/              # Pinia stores
│   │   ├── user.ts
│   │   ├── tasks.ts
│   │   └── bids.ts
│   ├── views/               # 页面级组件
│   │   ├── HomeView.vue
│   │   ├── TaskDetailView.vue
│   │   ├── CreateTaskView.vue
│   │   ├── ProfileView.vue
│   │   └── BossGanttView.vue
│   ├── components/          # 通用组件
│   │   └── AppHeader.vue
│   ├── router/              # 路由配置
│   ├── utils/               # 工具
│   ├── composables/         # 组合式函数（待填）
│   ├── styles/              # 全局样式
│   ├── types/               # 全局类型
│   ├── App.vue
│   └── main.ts
├── index.html
├── vite.config.ts
├── tailwind.config.js
├── postcss.config.js
├── tsconfig.json
├── env.d.ts
├── .env.example
└── package.json
```

## 🔧 环境配置

复制 `.env.example` 为 `.env.local` 并按需修改：

```bash
cp .env.example .env.local
```

| 变量 | 默认值 | 说明 |
|---|---|---|
| `VITE_API_BASE` | `/api` | API 基础地址 |
| `VITE_API_PROXY` | `http://localhost:8080` | 开发期后端代理目标 |

## 🎨 设计规范

设计 Token 见 [`../DESIGN.md`](../DESIGN.md)（Linear + Vercel 风格）。

主要颜色：
- **主色**：`#5e6ad2`（紫罗兰，Tailwind: `brand-500`）
- **状态色**：`blue-100/800`（OPEN）/ `orange-100/800`（ASSIGNED）/ `green-100/800`（COMPLETED）/ `gray-200/700`（FAILED）/ `gray-50/500`（CANCELLED）

## 🛣 路由

| Path | View | 备注 |
|---|---|---|
| `/` | HomeView | 任务大厅 |
| `/task/:id` | TaskDetailView | 任务详情 |
| `/create` | CreateTaskView | 发布任务 |
| `/profile` | ProfileView | 我的主页 |
| `/boss` | BossGanttView | 老板视图（需 boss 角色） |

## 🚧 当前状态

✅ **架构已就位**：
- 构建配置（Vite + TS + Tailwind）
- 路由（5 个 views）
- Pinia stores（user / tasks / bids）
- API 客户端（Axios + 拦截器）
- 类型定义（与后端 DTO 对齐）
- 全局样式（Tailwind + 状态徽章等组件类）
- AppHeader 组件

⏳ **待 Phase 1 实现**：
- 任务列表 + 搜索 + 筛选 + 分页
- 任务详情 + 投标流程
- 发布任务 + TipTap 富文本
- 个人主页 + 甘特图
- 老板视图 + 全局甘特图

## 📚 相关文档

- [需求文档 v0.4](../docs/需求文档.md)
- [技术选型 v0.5](../docs/技术选型.md)
- [模块与数据库设计 v0.6](../docs/模块与数据库设计.md)
- [原型（可点击演示）](../prototype/)
