# TaskHub Design Spec

> 项目代号：TaskHub
> 文档版本：v1
> 最后更新：2026-06-11
> 状态：原型阶段（含设计 token 锁定）

## 1. 设计语言

- **主参考**：Linear (`~/design-references/linear-app.DESIGN.md`)
- **副参考**：Vercel (`~/design-references/vercel.DESIGN.md`)

## 2. 设计 Token

### 2.1 颜色

| Token | 值 | 用途 |
|---|---|---|
| `--color-primary` | `#5e6ad2` | 主要操作、强调链接、active 状态 |
| `--color-primary-hover` | `#6872d9` | hover 态 |
| `--color-bg` | `#fafafa` | 页面背景 |
| `--color-surface` | `#ffffff` | 卡片、容器 |
| `--color-border` | `#ebebeb` | 默认边框 |
| `--color-border-hover` | `#d1d5db` | hover 边框 |
| `--color-text` | `#1a1a1a` | 主要文字 |
| `--color-text-muted` | `#6e6e80` | 次要文字 |
| `--color-text-subtle` | `#8f8f99` | 辅助文字、placeholder |

### 2.2 状态颜色（任务状态徽章 + 甘特图条）

| 状态 | 背景 | 文字 | 语义 |
|---|---|---|---|
| `DRAFT`（草稿） | `#f3f4f6` | `#6b7280` | 未公开 |
| `OPEN`（招标中） | `#dbeafe` | `#1e40af` | 蓝 |
| `ASSIGNED`（进行中） | `#fed7aa` | `#9a3412` | 橙 |
| `COMPLETED`（已完成） | `#d1fae5` | `#065f46` | 绿 |
| `FAILED`（流标） | `#e5e7eb` | `#4b5563` | 灰 |
| `CANCELLED`（已取消） | `#f9fafb` | `#9ca3af` + line-through | 浅灰 + 删除线 |

### 2.3 复杂度徽章

| 复杂度 | 背景 | 文字 | 语义 |
|---|---|---|---|
| `LOW`（简单） | `#d1fae5` | `#065f46` | 绿 - 工作量小 |
| `MEDIUM`（中等复杂） | `#f3f4f6` | `#374151` | 灰 - 中等 |
| `HIGH`（高度复杂） | `#fee2e2` | `#991b1b` | 红 - 工作量大 / 屎山 |

## 3. 字体

- 系统字体栈（无外部字体）
- 中文优先 PingFang SC / Microsoft YaHei
- 标题：`font-weight: 600`
- 正文：`font-weight: 400`
- 字号：12 / 14 / 16 / 18 / 20 / 24 / 30px

## 4. 间距

- 8px 基础网格
- 卡片 padding: `20px`（紧凑）/ `24px`（标准）
- 列表项 padding: `12px 16px`
- 区块间距: `24px` 或 `32px`

## 5. 圆角

- 元素: `6px`
- 卡片 / 容器: `8px`
- 按钮: `6px`
- 状态徽章: `9999px`（胶囊形）

## 6. 阴影

- card 静态: `0 1px 2px rgba(15, 23, 42, 0.04)`
- card hover: `0 4px 12px rgba(15, 23, 42, 0.08)`
- modal: `0 20px 50px rgba(15, 23, 42, 0.15)`

## 7. 组件约定

### 7.1 状态徽章
- 形状: 胶囊（圆角 9999px）
- 字号: 12px
- padding: `2px 8px`
- 颜色: 见 §2.2

### 7.2 按钮
- **主按钮**: bg `#5e6ad2`, text white, hover `#6872d9`
- **次按钮**: bg white, border `#ebebeb`, text `#1a1a1a`, hover bg `#fafafa`
- **危险按钮**: text `#dc2626`, hover bg `#fef2f2`
- 高度: 32px（紧凑）/ 36px（标准）

### 7.3 卡片
- bg white, border `1px solid #ebebeb`, radius `8px`
- padding: `20px`
- hover: border `#d1d5db`, shadow 增强

### 7.4 链接 / 导航
- 默认: `#1a1a1a`
- hover / active: `#5e6ad2`
- 下划线: 仅在文字链接

## 8. 项目特定规则

- **主题**: 仅浅色（暂不支持深色）
- **主色覆盖**: 不，使用 Linear 默认紫罗兰 `#5e6ad2`
- **不使用 Emoji 装饰**（用 SVG / 文字代替）
- **中文为主**，按钮文案简短（2-4 字）
- **数据格式**: 金额用 `¥XXX`，日期用 `YYYY-MM-DD`

## 9. 视图布局约定

| 视图 | 宽度 | 主要内容 |
|---|---|---|
| 任务大厅 | max-w-7xl | 筛选条 + 任务列表 |
| 任务详情 | max-w-5xl | 左主右辅（任务信息 + 投标列表） |
| 发布任务 | max-w-3xl | 单列表单 |
| 我的主页 | max-w-5xl | Tab 切换（我发布 / 我投标 / 我接） |
| 老板视图 | max-w-7xl | 筛选条 + 甘特图 |

## 10. 不要做的事

- ❌ 引入额外 UI 组件库（antd / element-plus / 等）
- ❌ 引入大依赖（图表库 / Gantt 库 / 等）
- ❌ 自定义字体
- ❌ 复杂动效（最多用 200ms ease-in-out）
- ❌ 暗色模式（暂不支持）
