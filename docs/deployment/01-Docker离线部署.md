# TaskHub Docker 离线部署

## 1. 目标

本方案面向“服务器只能临时连接一次公网，之后长期断网”的环境。

联网期间完成全部下载和镜像构建；断网后只使用两个本地镜像运行：

```text
taskhub-app:1.0.0
taskhub-web:1.0.0
```

MySQL 和 Redis 使用已有远程服务，不在 TaskHub Compose 中重复部署。

## 2. 联网前准备

### 2.1 服务器要求

服务器需要提前具备：

```text
Docker Engine
至少 15GB 可用磁盘空间
```

宿主机不需要安装 PHP、Composer、Node.js、npm 或 Nginx。已有 Docker Compose 和 Buildx 时直接使用；缺失时，联网脚本才会下载官方固定版本并安装到当前用户的 Docker CLI 插件目录。

当前部署目标已经固定为 `x86_64`。脚本检测到其他 CPU 架构时会直接停止，避免下载错误架构的二进制文件。

### 2.2 上传项目

把完整项目上传到服务器，例如：

```text
/opt/taskhub
```

必须包含以下部署文件：

```text
Dockerfile
compose.yaml
.dockerignore
docker/
deploy/
composer.json
composer.lock
package.json
package-lock.json
```

### 2.3 准备生产配置

生产配置不参与镜像构建，因此不必为了联网下载提前收齐。可以在联网前准备，也可以在断网后、首次启动前完成。

在项目根目录执行：

```bash
cp .env.production.example .env.production
```

编辑 `.env.production`，至少填写：

```text
APP_KEY
APP_URL
MySQL 连接
Redis 连接
SSO 配置
人员、付款账号和文件接口配置
SMTP 配置
```

生成 `APP_KEY` 时不需要在宿主机安装 PHP，可以暂时用 Docker；但这会要求 PHP 镜像已经拉取。因此更适合在开发机提前执行：

```bash
php artisan key:generate --show
```

把输出的 `base64:...` 写入生产配置，不要提交真实密钥。

## 3. 临时公网需要覆盖的下载来源

联网窗口内会访问以下资源：

| 来源 | 下载内容 |
| --- | --- |
| Docker Hub | PHP、Node、Composer、Nginx 基础镜像 |
| GitHub Releases | 仅在服务器缺少时下载 Docker Compose 和 Buildx 插件 |
| PECL | `redis-6.3.0` PHP 扩展 |
| Packagist、GitHub | `composer.lock` 中的 PHP 依赖 |
| npm Registry | `package-lock.json` 中的前端依赖 |
| Bunny Fonts | Instrument Sans 字体文件 |

### 3.1 x86_64 固定下载地址

Docker Compose `v5.7.1`：

```text
https://github.com/docker/compose/releases/download/v5.7.1/docker-compose-linux-x86_64
```

SHA-256：

```text
af426722a8af5de812fcee30cc1c39f0639f5f723cbadefb25e5d4db054064cf
```

Docker Buildx `v0.39.1`：

```text
https://github.com/docker/buildx/releases/download/v0.39.1/buildx-v0.39.1.linux-amd64
```

SHA-256：

```text
903090816da68f8bc1a2ff5346dd163a5adacfce4548d003c75df42cd47a9bf4
```

PHP Redis 扩展 `6.3.0`：

```text
https://pecl.php.net/get/redis-6.3.0.tgz
```

SHA-256：

```text
0d5141f634bd1db6c1ddcda053d25ecf2c4fc1c395430d534fd3f8d51dd7f0b5
```

Instrument Sans 字体 CSS：

```text
https://fonts.bunny.net/css2?family=Instrument+Sans:wght@400;500;600&display=swap&subset=latin
```

Composer 和 npm 的每个压缩包地址分别固定在 `composer.lock` 和 `package-lock.json` 中。用于申请的确定仓库入口已经整理在 `deploy/external-download-urls.txt`。

最稳妥的网络申请方式是临时开放服务器出站 TCP 80/443。软件仓库和镜像仓库可能跳转到 CDN，只开放单个域名容易遗漏。

如果必须按域名放行，至少需要：

```text
auth.docker.io
registry-1.docker.io
production.cloudfront.docker.com
hub.docker.com
pecl.php.net
repo.packagist.org
packagist.org
api.github.com
github.com
codeload.github.com
objects.githubusercontent.com
release-assets.githubusercontent.com
registry.npmjs.org
fonts.bunny.net
```

如果服务器配置了第三方 Docker Registry Mirror，必须确认它仍然可用；失效镜像代理会让官方镜像拉取返回 403。临时联网期间优先直接访问上面的 Docker Hub 官方地址。

## 4. 唯一的联网构建命令

进入项目目录：

```bash
cd /opt/taskhub
```

执行：

```bash
bash deploy/online-build.sh
```

脚本会自动完成：

1. 检查 Docker Compose 和部署文件；构建阶段使用公开配置模板，不读取生产密钥。
2. 必要时下载并校验 Docker Compose 和 Buildx 插件。
3. 并行拉取四个固定版本的官方基础镜像。
4. 使用 PHP 官方镜像自带源码编译 `pdo_mysql` 和 `opcache`。
5. 下载并编译固定版本的 `redis` 扩展；`curl`、`mbstring` 和 `pdo_sqlite` 已由官方镜像提供。
6. 严格按照 `composer.lock` 安装生产 PHP 依赖。
7. 严格按照 `package-lock.json` 安装 npm 依赖。
8. 执行 TypeScript 检查和 Vite 生产构建。
9. 下载并打包 Instrument Sans 字体。
10. 生成 `taskhub-app:1.0.0` 和 `taskhub-web:1.0.0`。
11. 检查最终 PHP 扩展和 Laravel 启动状态。

脚本最后显示以下文字后，公网下载任务才算完成：

```text
联网下载和构建已经完成
最终镜像：taskhub-app:1.0.0
最终镜像：taskhub-web:1.0.0
```

网络中途失败时不要清理 Docker 缓存，保持联网后重新执行同一条命令。Docker 会复用已经完成的镜像层和依赖缓存。

## 5. 断网后启动

执行：

```bash
cd /opt/taskhub
bash deploy/offline-start.sh
```

脚本强制使用本地镜像，不会执行 pull 或 build。

默认访问地址：

```text
http://服务器地址:18080
```

修改宿主机端口时执行：

```bash
TASKHUB_HTTP_PORT=19080 bash deploy/offline-start.sh
```

## 6. 运行容器

Compose 会运行三个容器：

| 容器 | 镜像 | 职责 |
| --- | --- | --- |
| `taskhub-app` | `taskhub-app:1.0.0` | Laravel PHP-FPM |
| `taskhub-scheduler` | `taskhub-app:1.0.0` | 每分钟执行 Laravel Scheduler |
| `taskhub-web` | `taskhub-web:1.0.0` | Nginx 静态资源与反向代理 |

Scheduler 复用应用镜像，因此不需要下载第三个 TaskHub 镜像。

## 7. 导出离线镜像

关闭公网后也可以执行：

```bash
bash deploy/export-images.sh
```

生成：

```text
deploy/artifacts/taskhub-runtime-1.0.0.tar
deploy/artifacts/SHA256SUMS
```

在另一台离线服务器恢复：

```bash
docker load --input taskhub-runtime-1.0.0.tar
```

## 8. 重要边界

最终镜像足够在断网环境部署当前版本。如果以后修改 PHP 或前端依赖、PHP 扩展、基础镜像版本，仍然需要新的联网构建窗口。

TaskHub 断开公共互联网后，仍需要通过公司内部网络访问：

```text
MySQL
Redis
SSO
人员接口
付款账号接口
文件接口
头像接口
SMTP
```

这些是运行时内部服务，不属于 Docker 公网下载。
