# 04-SSO接入

## 1. 本章节目标

本章定义真实 SSO 的接入边界。Codex 不调试公司接口，但代码必须预留真实调用位置，禁止 Mock 登录和硬编码工号。

## 2. 涉及文件

| 文件 | 作用 |
|---|---|
| `app/Integrations/Sso/SsoClient.php` | 调用公司 SSO 的客户端 |
| `app/Integrations/Sso/SsoUser.php` | SSO 返回的当前用户对象 |
| `app/Integrations/Sso/SsoException.php` | SSO 异常 |
| `app/Auth/SsoUserProvider.php` | Laravel Auth 内部占位 Provider，不查询本地用户表 |
| `app/Services/CurrentUserService.php` | 项目统一获取当前用户的服务 |
| `config/sso.php` | SSO 地址、路径、超时配置 |
| `.env.example` | SSO 环境变量模板 |

## 3. 实现步骤

1. Controller 不直接读取 Session。
2. `CurrentUserService` 从当前 Request 读取 Bearer Token。
3. `CurrentUserService` 调用 `SsoClient`。
4. `SsoClient` 负责真实 HTTP 请求，具体公司协议位置用 `// TODO:` 标注。
5. `SsoUser` 将返回数据转换为稳定对象，对外暴露 `employeeNo()`。
6. `SsoUserProvider` 只用于满足 Laravel 内部 Auth guard 解析，不读取数据库、不创建本地用户。

## 4. 核心代码讲解

Java 中常见写法是：

```java
CurrentUser currentUser = currentUserService.currentUser();
String employeeNo = currentUser.employeeNo();
```

Laravel 中对应：

```php
$employeeNo = app(CurrentUserService::class)->employeeNo();
```

后续 Controller 可以通过构造函数注入：

```php
public function __construct(private CurrentUserService $currentUser) {}
```

这类似 Spring 的构造函数注入。Laravel 容器会根据类型自动解析依赖。

## 5. 设计原因

不用 Mock SSO 的原因：

- 从第一阶段开始固定真实认证边界，避免后续替换 Mock 时重构。
- 当前不能调试公司接口，但可以先定义客户端、返回对象和异常。
- 所有代码统一依赖 `CurrentUserService`，后续公司协议变化只改 SSO 集成层。

不用 Laravel 默认 Auth 的原因：

- TaskHub 不维护本地 users 表。
- 人员标识是公司工号。
- 权限判断依赖实时外部人员接口，而不是本地用户状态。

保留一个 `sso` guard 的原因是 Laravel 生态中的日志、调试或中间件可能会调用 `Auth::hasUser()`。该 guard 绑定到 `SsoUserProvider`，Provider 所有检索方法都返回 `null`，因此不会引入本地用户体系。

## 6. 注意事项

- 禁止在 Controller 到处写 `$request->session()` 读取登录人。
- 禁止硬编码工号。
- `SsoClient` 中 TODO 必须在拿到公司接口协议后补齐。
- SSO 失败统一抛 `SsoException`，后续再接入异常展示或跳转策略。

## 7. 本阶段完成效果

当前阶段完成了真实 SSO 的代码边界：

- 有统一当前用户服务。
- 有 SSO 客户端。
- 有 SSO 返回对象。
- 没有 Mock 登录。

## 8. 下一步

后续进入权限或业务模块前，应先补齐公司 SSO 接口协议，并决定未登录请求是跳转公司登录页还是返回 401。
