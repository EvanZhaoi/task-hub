# 05-Inertia与React初始化

## 1. 本章目标

理解 Laravel Route 如何通过 Inertia 渲染 React 页面，并掌握 PHP props 到 TypeScript props 的传递方式。

## 2. 前置条件

- 已安装 npm 依赖。
- 已能运行 `npm run build`。

## 3. 本章最终效果

访问 `/` 时，Laravel 执行 `Inertia::render('Welcome')`，React 渲染 `resources/js/Pages/Welcome.tsx`。

## 4. 实际执行命令

```bash
npm run dev
php artisan serve
```

或：

```bash
composer run dev
```

生产构建：

```bash
npm run build
```

## 5. 命令执行目录

项目根目录。

## 6. 命令作用

- `npm run dev`：启动 Vite 开发服务器。
- `php artisan serve`：启动 Laravel 开发服务器。
- `composer run dev`：同时启动 Laravel 和 Vite。
- `npm run build`：生成生产构建产物。

## 7. 预期输出或生成文件

开发模式：

```text
Server running on [http://127.0.0.1:8000]
VITE ready
```

生产构建会生成但不提交：

```text
public/build/
```

## 8. 逐文件修改过程

### 8.1 `resources/views/app.blade.php`

这个文件是 Inertia 根 HTML：

```blade
@vite(['resources/css/app.css', 'resources/js/app.tsx'])
@inertiaHead
@inertia
```

`@inertia` 是 React 挂载点。

### 8.2 `resources/js/app.tsx`

React 入口负责加载页面：

```tsx
const pages = import.meta.glob<PageModule>('./Pages/**/*.tsx');
return (await page()).default;
```

### 8.3 `resources/js/Pages/Welcome.tsx`

TypeScript props：

```tsx
type WelcomeProps = {
    appName: string;
};
```

### 8.4 `app/Http/Middleware/HandleInertiaRequests.php`

指定根视图：

```php
protected $rootView = 'app';
```

### 8.5 `routes/web.php`

PHP props 传递：

```php
return Inertia::render('Welcome', [
    'appName' => config('app.name'),
]);
```

## 9. 修改前后的关键代码

传统 Blade：

```php
return view('welcome');
```

Inertia：

```php
return Inertia::render('Welcome', ['appName' => config('app.name')]);
```

## 10. 核心原理解释

请求链路：

```text
Browser
→ Laravel Route
→ Inertia::render('Welcome', props)
→ resources/views/app.blade.php
→ resources/js/app.tsx
→ resources/js/Pages/Welcome.tsx
```

PHP 数组会变成页面 props，React 组件用 TypeScript 类型接收。

## 11. 与 Spring Boot 的概念对照

| Inertia 项目 | Spring Boot 类比 |
|---|---|
| `Inertia::render()` | 返回 ModelAndView |
| `app.blade.php` | 根 HTML 模板 |
| React Page | 前端 View |
| Page Props | Model attributes |
| Vite | 前端构建插件 |

## 12. 验证步骤

```bash
npm run typecheck
npm run build
php artisan test
curl -I http://127.0.0.1:8000
```

预期 `curl` 返回 `200 OK`。

## 13. 常见错误与解决方法

| 错误 | 处理 |
|---|---|
| `Page not found` | 检查 `Inertia::render()` 名称和 `Pages` 文件名 |
| `@vite` 找不到文件 | 检查 `vite.config.ts` input |
| Props 类型报错 | 同步 PHP props 和 TypeScript 类型 |
| 页面空白 | 打开浏览器控制台检查 Vite 和 React 错误 |

## 14. 操作检查清单

- [ ] `app.blade.php` 存在
- [ ] `app.tsx` 使用 `createInertiaApp`
- [ ] `Welcome.tsx` 能接收 props
- [ ] `routes/web.php` 返回 Inertia 页面
- [ ] Vite 构建通过

## 15. 本章对应的实际项目文件

- `resources/views/app.blade.php`
- `resources/js/app.tsx`
- `resources/js/Pages/Welcome.tsx`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `vite.config.ts`
- `routes/web.php`

## 16. 下一章入口

继续阅读 [06-SSO认证流程](./06-SSO认证流程.md)。
