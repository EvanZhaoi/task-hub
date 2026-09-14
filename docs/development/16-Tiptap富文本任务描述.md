# 16-Tiptap 富文本任务描述

## 本章目标

本章把发布任务中的“任务描述”从普通 `textarea` 升级为 Tiptap 富文本编辑器。

本次只处理任务描述输入、展示和 HTML 安全清洗，不改变数据库字段、不改变发布任务接口结构。

## 学习目标

完成本章后，你应该理解：

- 为什么富文本编辑器不能直接写在 `Tasks/Index.tsx` 页面里。
- Tiptap、React 组件、Inertia 表单之间如何传递 HTML 字符串。
- 为什么前端生成的 HTML 不能直接信任。
- Laravel 后端如何在保存前做白名单清洗。
- Viewer 为什么统一封装 `dangerouslySetInnerHTML`。

## 最终效果

发布任务弹窗中：

- `任务描述` 使用 `RichTextEditor`。
- 支持粗体、斜体、删除线、H2/H3、无序/有序列表、引用、行内代码、代码块、链接、撤销/重做。
- `description` 仍然提交 HTML 字符串。
- 后端保存前使用 `RichTextSanitizer` 白名单清洗。
- 任务大厅卡片使用 `RichTextViewer` 展示描述。

暂不做：

- 图片
- 表格
- 字体颜色
- 文件上传
- @人员
- 协同编辑
- Tiptap 付费功能

## 涉及文件

| 文件 | 作用 |
|---|---|
| `package.json` / `package-lock.json` | 增加 Tiptap 开源依赖 |
| `composer.json` / `composer.lock` | 增加 HTMLPurifier |
| `app/Services/RichTextSanitizer.php` | 后端 HTML 白名单清洗 |
| `app/Http/Controllers/TaskController.php` | 保存和输出任务描述时调用清洗服务 |
| `resources/js/components/common/RichTextEditor.tsx` | 可复用富文本编辑器 |
| `resources/js/components/common/RichTextViewer.tsx` | 可复用富文本展示组件 |
| `resources/js/Pages/Tasks/Index.tsx` | 发布任务弹窗接入编辑器，任务卡片接入 Viewer |
| `resources/css/app.css` | 富文本编辑区和展示区基础排版 |
| `tests/Feature/ApplicationShellTest.php` | 验证危险 HTML 会被清理 |

## 实际执行命令

命令执行目录：

```text
task-hub 项目根目录
```

安装 Tiptap 开源依赖：

```bash
npm install @tiptap/react @tiptap/starter-kit @tiptap/extension-link @tiptap/extension-placeholder --registry=https://registry.npmjs.org/
```

安装后端 HTML 清洗库：

```bash
composer require ezyang/htmlpurifier
```

生成清洗服务类：

```bash
php artisan make:class Services/RichTextSanitizer
```

验证命令：

```bash
npm run typecheck
npm run build
php artisan test
```

## 每一步操作

### 1. 新增 RichTextEditor

文件：

```text
resources/js/components/common/RichTextEditor.tsx
```

为什么放在 `components/common`：

- 它不是 shadcn 基础组件。
- 它是 TaskHub 业务表单可复用的组合组件。
- 页面只需要关心 `value` 和 `onChange`，不应该知道 Tiptap 的 toolbar、extension、editor command 细节。

核心代码：

```tsx
const editor = useEditor({
    // value 来自 Inertia useForm 中的 description。
    content: value,
    editable: !disabled,
    extensions: [
        StarterKit.configure({
            // 第一版只开放 H2/H3，避免任务描述里出现过大的 H1。
            heading: {
                levels: [2, 3],
            },
        }),
        Link.configure({
            // 编辑时点击链接不跳转，避免误打开外部页面。
            openOnClick: false,
            HTMLAttributes: {
                rel: 'noopener noreferrer',
                target: '_blank',
            },
        }),
        Placeholder.configure({
            // Placeholder 只影响编辑时提示，不会进入最终 HTML。
            placeholder,
        }),
    ],
    onUpdate: ({ editor }) => {
        // description 继续作为 HTML 字符串提交，不改变后端接口结构。
        onChange(editor.getHTML());
    },
});
```

### 2. 工具栏只调用 Tiptap 命令

工具栏按钮示例：

```tsx
<ToolbarButton
    active={editor?.isActive('bold')}
    disabled={disabled || !editor}
    label="粗体"
    onClick={() => editor?.chain().focus().toggleBold().run()}
>
    <Bold className="size-4" />
</ToolbarButton>
```

理解方式：

- `chain()` 表示连续执行一组编辑器命令。
- `focus()` 先把光标放回编辑器。
- `toggleBold()` 切换粗体。
- `run()` 真正执行命令。

这和 Spring Boot 没有直接对应关系，更像前端富文本编辑器自己的命令 API。

### 3. 新增 RichTextViewer

文件：

```text
resources/js/components/common/RichTextViewer.tsx
```

核心代码：

```tsx
export function RichTextViewer({ className, html }: RichTextViewerProps) {
    if (!html) {
        return <p className={cn('text-sm leading-6 text-[#6e6e80]', className)}>暂无描述</p>;
    }

    return (
        <div
            // description 入库前已经由后端 RichTextSanitizer 白名单清洗。
            // Viewer 统一集中使用 dangerouslySetInnerHTML，避免页面里到处散落危险 API。
            className={cn('rich-text-viewer text-sm leading-6 text-[#6e6e80]', className)}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
```

为什么需要 Viewer：

- React 默认会把字符串当普通文本输出，不会解析 HTML。
- 富文本描述需要按 HTML 展示。
- `dangerouslySetInnerHTML` 是危险 API，必须集中封装，不能散落在页面组件中。

### 4. 发布任务页面接入

文件：

```text
resources/js/Pages/Tasks/Index.tsx
```

任务描述字段改为：

```tsx
<Field label="任务描述" message={form.errors.description}>
    <RichTextEditor
        disabled={form.processing}
        name="description"
        onChange={(value) => form.setData('description', value)}
        placeholder="说明背景、目标、验收标准和注意事项"
        value={form.data.description}
    />
</Field>
```

任务卡片展示改为：

```tsx
<RichTextViewer className="mt-2 max-h-24 max-w-5xl overflow-hidden" html={task.description} />
```

### 5. 后端保存前清洗 HTML

文件：

```text
app/Services/RichTextSanitizer.php
```

核心规则：

```php
$config->set(
    'HTML.Allowed',
    implode(',', [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        's',
        'strike',
        'h2',
        'h3',
        'ul',
        'ol',
        'li',
        'blockquote',
        'code',
        'pre',
        'a[href|target|rel]',
    ]),
);

$config->set('URI.AllowedSchemes', [
    'http' => true,
    'https' => true,
    'mailto' => true,
]);
```

为什么必须后端清洗：

- 前端代码可以被绕过。
- 用户可以直接用工具 POST 任意 HTML。
- 如果直接保存 `<script>`、`onclick`、`javascript:`，Viewer 展示时可能造成 XSS。
- 后端才是可信边界。

### 6. Controller 使用清洗服务

文件：

```text
app/Http/Controllers/TaskController.php
```

保存前：

```php
$validated = $request->validated();

// 前端提交的是 Tiptap HTML，入库前必须做白名单清洗。
$validated['description'] = $richTextSanitizer->clean($validated['description'] ?? null);
```

列表输出时：

```php
'description' => $richTextSanitizer->clean($task->description),
```

为什么输出时也清洗一次：

- 新数据会保存前清洗。
- 但数据库里可能已经有旧数据。
- Viewer 会渲染 HTML，因此输出时再清洗一次更稳。

## 核心原理解释

Tiptap 的数据流：

```text
用户在编辑器输入
↓
Tiptap 更新内部 ProseMirror 文档
↓
editor.getHTML()
↓
Inertia useForm.description
↓
POST /tasks
↓
Laravel FormRequest 校验字符串长度
↓
RichTextSanitizer 白名单清洗
↓
task.description 保存 HTML
↓
RichTextViewer 展示清洗后的 HTML
```

## 与 Spring Boot 的概念对照

| TaskHub / Laravel | Spring Boot 类比 | 说明 |
|---|---|---|
| `RichTextEditor.tsx` | 前端表单组件 | 负责输入，不负责安全边界 |
| `RichTextViewer.tsx` | 前端展示组件 | 统一封装 HTML 展示 |
| `StoreTaskRequest` | `@Valid` / DTO Validation | 校验字段类型和长度 |
| `RichTextSanitizer` | Service / Component | 负责 HTML 白名单清洗 |
| `TaskController@store` | Controller 方法 | 组合校验、清洗、写库 |
| HTMLPurifier | Jsoup Safelist | 类似 Java 里用 Jsoup 做 HTML whitelist |

## 常见错误

| 问题 | 原因 | 处理 |
|---|---|---|
| 富文本内容显示成 `<p>...</p>` 字符串 | 直接用 `{description}` 输出 | 使用 `RichTextViewer` |
| XSS 测试还能保存 `script` | 没有调用 `RichTextSanitizer` | 检查 `TaskController@store` |
| 链接点击不安全 | 允许了 `javascript:` URL | 检查 `URI.AllowedSchemes` |
| 编辑器重置后仍显示旧内容 | Tiptap 内部状态没有同步父组件 value | 检查 `editor.commands.setContent(value, { emitUpdate: false })` |
| 工具栏按钮点击提交了表单 | 按钮没有 `type="button"` | 检查 `ToolbarButton` |

## 如何验证

自动验证：

```bash
npm run typecheck
npm run build
php artisan test
```

手动验证：

1. 打开 `/tasks`。
2. 点击“发布任务”。
3. 在任务描述中输入多段内容。
4. 点击粗体、斜体、列表、引用、代码块、链接。
5. 提交任务。
6. 任务大厅卡片能展示富文本摘要样式。
7. 后端测试确认 `<script>`、`onclick`、`javascript:` 不会入库。

## 本章总结

本次实现遵循两个原则：

- 前端负责编辑体验，后端负责安全边界。
- 页面只组合 `RichTextEditor` / `RichTextViewer`，不直接写 Tiptap 细节。

## 下一章预告

下一步可以继续推进投标功能。

投标功能会开始涉及：

- Bid 表写入
- BidMember 写入
- 同一任务同一 OWNER 只能有一条 ACTIVE 投标
- 投标事务
- 任务事件 `BID_SUBMITTED`
