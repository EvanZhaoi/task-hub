# 06-SSO认证流程

## 1. 本章目标

说明 TaskHub 的真实 SSO 接入边界。当前总部协议未知，文档只定义架构位置和待确认事项，不编造接口。

## 2. 前置条件

- 已理解 Laravel + Inertia 页面访问方式。
- 已知道 TaskHub 不创建本地 users 表。

## 3. 本章最终效果

开发者知道当前代码如何获取当前用户，以及后续真实 SSO 协议确认后应该修改哪些文件。

## 4. 实际执行命令

查看相关文件：

```bash
find app/Integrations/Sso app/Services app/Http/Middleware -type f | sort
php artisan route:list
```

## 5. 命令执行目录

项目根目录。

## 6. 命令作用

- `find`：查看 SSO 骨架文件。
- `route:list`：确认当前未启用受保护业务路由。

## 7. 预期输出或生成文件

你会看到：

```text
app/Integrations/Sso/SsoClient.php
app/Integrations/Sso/SsoUser.php
app/Services/CurrentUserService.php
app/Http/Middleware/EnsureSsoAuthenticated.php
```

本章不生成文件。

## 8. 逐文件修改过程

### 8.1 `.env.example`

当前 SSO 配置全部为空或占位：

```env
SSO_BASE_URL=
SSO_LOGIN_URL=
SSO_CALLBACK_PATH=/sso/callback
SSO_VALIDATE_PATH=
SSO_TIMEOUT=5
```

除 `SSO_CALLBACK_PATH` 是本项目预留路由外，其余真实值待总部协议确认。

### 8.2 `SsoClient`

真实 HTTP 调用位置：

```php
// TODO: Align request path, authentication headers, and payload shape with the company SSO contract.
```

### 8.3 `CurrentUserService`

当前顺序：

1. 先读取 Laravel Session 中的 `sso_user`。
2. 如果没有 Session，再兼容 Bearer Token。

### 8.4 `EnsureSsoAuthenticated`

该中间件当前未注册，只保留未来受保护页面使用的骨架。真实跳转 URL、state 参数、callback 参数名都等待总部协议确认。

## 9. 修改前后的关键代码

早期 Bearer-only 方式：

```php
$token = $this->request->bearerToken();
```

当前骨架：

```php
$sessionUser = $this->request->session()->get(self::SESSION_KEY);

if (is_array($sessionUser)) {
    return SsoUser::fromPayload($sessionUser);
}
```

## 10. 核心原理解释

SSO 有两种可能模式。

模式 A：Bearer Token API

```text
前端每次请求携带 Authorization Header
→ Laravel 读取 Bearer Token
→ 调公司 SSO 验证
→ 得到工号
```

模式 B：SSO Callback + Laravel Session（TaskHub 推荐）

```text
访问受保护页面
→ 跳转公司 SSO
→ 回调 Laravel
→ Laravel 验证 Token/Code
→ 保存 Session
→ 返回原页面
```

TaskHub 是 Laravel + Inertia 单体应用，浏览器页面导航更适合模式 B。

## 11. 与 Spring Boot 的概念对照

| Laravel | Spring Boot |
|---|---|
| Middleware | Filter / HandlerInterceptor |
| Session | HttpSession |
| `CurrentUserService` | 当前用户上下文 Service |
| `SsoClient` | 外部认证系统 Client |
| `SsoException` | 认证异常 |

## 12. 验证步骤

当前没有真实总部 SSO，不能验证真实登录。可验证骨架：

```bash
php artisan test
php artisan route:list
```

## 13. 常见错误与解决方法

| 错误 | 处理 |
|---|---|
| 误以为 `SSO_VALIDATE_PATH` 是真实接口 | 当前为空，待总部确认 |
| 想添加 Mock 登录 | 不允许，避免后续替换成本 |
| 想创建 users 表 | 不允许，人员来自外部接口 |
| 受保护页面直接 401 | 当前中间件未接真实 SSO，属预期 |

## 14. 操作检查清单

- [ ] 没有本地 users 表
- [ ] 没有 Mock 登录
- [ ] 没有硬编码工号
- [ ] SSO 真实参数仍为空
- [ ] 后续统一通过 `CurrentUserService` 获取当前用户

## 15. 本章对应的实际项目文件

- `app/Integrations/Sso/SsoClient.php`
- `app/Integrations/Sso/SsoUser.php`
- `app/Integrations/Sso/SsoException.php`
- `app/Services/CurrentUserService.php`
- `app/Http/Middleware/EnsureSsoAuthenticated.php`
- `config/sso.php`
- `.env.example`

## 16. 下一章入口

继续阅读 [07-Eloquent-Model设计](./07-Eloquent-Model设计.md)。
