# TaskHub 原型图

> TaskHub 内部任务交易平台 · 完整 HTML 原型图集
> 设计语言：Linear-inspired（紫罗兰 #5e6ad2）+ Vercel
> 共 16 个页面 + 1 个导航主页

## 🎯 用途

本文档是 TaskHub 产品的**完整视觉原型**，覆盖需求文档（[需求文档 v0.5](../需求文档.md) §5）的**全部 11 个核心流程（A-K）**。

可直接用于：
- ✅ 产品 / 设计评审（打开浏览器逐张看）
- ✅ 前后端联调参考（每页面对应一个 Story）
- ✅ 文档插图（P15、P16 可直接复用 SVG）
- ✅ 演示 / 给老板看（不要 demo 数据，他一眼就知道在做什么）

## 📂 文件结构

```
docs/prototype/
├── README.md                ← 本文件
├── index.html               ← 入口（导航主页，缩略图列表）
├── assets/
│   └── style.css            ← 共用样式（Design tokens · 共 1 个）
└── pages/                   ← 16 个原型页（自包含，可单独打开）
    ├── 01-overview.html         系统总览 + 状态机 + 权限矩阵
    ├── 02-login.html            SSO 登录跳转
    ├── 03-task-hall.html        任务大厅（搜索 + 筛选 + 分页）
    ├── 04-task-detail.html      任务详情（4 状态视图）
    ├── 05-create-task.html      发布任务（流程 A）
    ├── 06-direct-assign.html    直接指名（流程 D）
    ├── 07-bid.html              投标（流程 B）
    ├── 08-select-bid.html       选标 + 调整（流程 C）
    ├── 09-extension.html        协商延期（流程 E）
    ├── 10-cancel.html           取消任务（流程 F）
    ├── 11-failed-clone.html     流标 + 复制新建（流程 H）
    ├── 12-profile.html          个人主页
    ├── 13-boss-gantt.html       老板视图
    ├── 14-notifications.html    通知中心
    ├── 15-flow-diagrams.html    11 个流程的 SVG 图解
    └── 16-empty-states.html     12 种空状态合集
```

## 🚀 如何查看

### 方式 1：直接打开（最简单）

```bash
open ~/Projects/task-hub/docs/prototype/index.html
```

浏览器自动打开导航页，点任一卡片进入具体页面。

### 方式 2：起一个本地服务器（推荐）

```bash
cd ~/Projects/task-hub/docs/prototype
python3 -m http.server 8765
# 然后浏览器访问 http://localhost:8765/
```

本地服务器的好处：
- 字体加载更稳定
- 可以用浏览器的「前进 / 后退」翻页
- 文件链接（`./style.css`）以 `http://` 协议加载

### 方式 3：VS Code Live Server

右键 `index.html` → "Open with Live Server"

## 📋 流程覆盖映射

| 需求文档 § | 流程 | 原型页 |
|---|---|---|
| §5.1  | 流程 A · 任务发布（招标） | [P05](./pages/05-create-task.html) |
| §5.2  | 流程 B · 投标（单人 + 多人协作） | [P07](./pages/07-bid.html) |
| §5.3  | 流程 C · 选标 + 调整金额/交期 | [P08](./pages/08-select-bid.html) |
| §5.4  | 流程 D · 直接指名 | [P06](./pages/06-direct-assign.html) |
| §5.5  | 流程 E · 协商延期 | [P09](./pages/09-extension.html) |
| §5.6  | 流程 F · 取消任务 | [P10](./pages/10-cancel.html) |
| §5.7  | 流程 G · 完成确认 | （在 P04 任务详情页） |
| §5.8  | 流程 H · 流标 + 复制新建 | [P11](./pages/11-failed-clone.html) |
| §5.9  | 流程 I · 任务详情页布局 | [P04](./pages/04-task-detail.html) |
| §5.10 | 流程 J · 任务搜索 | （在 P03 任务大厅） |
| §5.11 | 流程 K · 列表分页 | （在 P03 任务大厅） |
| §8    | 通知规则 | [P14](./pages/14-notifications.html) |
| §9    | 老板视图 | [P13](./pages/13-boss-gantt.html) |
| §10   | 个人主页 | [P12](./pages/12-profile.html) |
| §4    | 状态机图 | [P01](./pages/01-overview.html) · [P15](./pages/15-flow-diagrams.html) |
| §7    | 权限矩阵 | [P01](./pages/01-overview.html) |
| —      | 全部 SVG 流程图 | [P15](./pages/15-flow-diagrams.html) |
| —      | 12 种空状态 | [P16](./pages/16-empty-states.html) |

**覆盖度：100%**——需求文档 §5 的每个流程都有对应原型页。

## 🎨 设计 Token

所有页面共用 [`assets/style.css`](./assets/style.css)，遵循 [`../../DESIGN.md`](../../DESIGN.md) 的规范：

| Token | 值 | 用途 |
|---|---|---|
| `--color-primary` | `#5e6ad2` | 主色 · 按钮 · 链接 |
| `--color-bg` | `#fafafa` | 页面背景 |
| `--color-surface` | `#ffffff` | 卡片 |
| `--color-border` | `#ebebeb` | 默认边框 |
| 字体 | 系统字体栈 | PingFang SC / Microsoft YaHei |
| 圆角 | 6px (元素) / 8px (卡片) | Linear 风格 |
| 图标 | Lucide (CDN) | 不用 emoji |
| 字体加载 | 无外部字体 | DESIGN.md §3 锁定 |

## 🔧 技术说明

### 自包含
- 每个 HTML 文件独立可运行（双击即开）
- 只依赖 1 个外部 CDN：`https://unpkg.com/lucide@latest`
- 共用样式：`./assets/style.css`

### 静态
- **纯静态 HTML，无 JS 框架**（不用 Alpine.js / Vue / React）
- "原型" 的定义 = 视觉状态，不需要交互
- 只有「浮动页间导航」按钮是纯 CSS + `<a href>` 跳转

### Mock 数据
- 所有用户、任务、投标等数据**内嵌在 HTML 里**（方便截图）
- 命名带 `MOCK_` 前缀，**绝对不允许出现真实业务数据**
- 不用 `localStorage` / 后端

### 与现有 prototype/ 的关系
- `prototype/`（根目录）：是 v0.4 的**可交互**原型（Alpine.js + Quill 富文本）
- `docs/prototype/`（本目录）：是 **v1 完整视觉稿**（静态 HTML · 16 页）
- 两者互补：根目录那个跑得动，但覆盖少；本目录覆盖全但只是图
- 未来 Phase 1 前端开发时，可以用本目录做 Story 的视觉参考

## 📦 体积统计

- 总共 **17 个文件**（1 主页 + 16 页 + 1 css）
- 总大小约 **220 KB**（未 gzip，gzip 后约 50KB）
- 单页最大 [P15](./pages/15-flow-diagrams.html) = 32KB（SVG 流程图最多）
- 单页最小 [P02](./pages/02-login.html) = 5KB（登录页简单）

## 🔄 更新日志

| 版本 | 日期 | 说明 |
|---|---|---|
| v1.0 | 2026-07-02 | 初版 · 16 页完整覆盖需求文档 §5 全部流程 |

## 💡 引用建议

写作文档或做演示时，可以这样引用：

```markdown
![任务大厅](docs/prototype/pages/03-task-hall.html)
```

或在 FlowUs / Notion 中用 iframe 嵌入：

```html
<iframe src="http://localhost:8765/prototype/pages/03-task-hall.html" 
        width="100%" height="800px" frameborder="0"></iframe>
```

---

> 🎯 **目标读者**：产品 / 设计 / 前端 / 后端 / 老板 · 任何人 5 分钟内能看懂 TaskHub 在做什么。
