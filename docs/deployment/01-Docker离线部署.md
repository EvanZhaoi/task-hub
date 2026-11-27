# TaskHub Docker 离线部署

## 1. 流程

部署分为三步：

```text
开发机准备项目包
→ 服务器联网下载基础镜像和 Redis 扩展
→ 服务器断网后构建 TaskHub 镜像
```

所有脚本都不会启动 TaskHub。

## 2. 开发机准备项目包

开发机已有 PHP、Composer、Node.js 和全部依赖，因此先执行：

```bash
composer install --no-dev --optimize-autoloader
npm run build
bash deploy/prepare-release.sh
```

生成：

```text
deploy/artifacts/taskhub-source-1.0.0.tar.gz
```

该压缩包包含源代码、`vendor/` 和 `public/build/`，不包含 `node_modules/`、Git 历史和真实环境变量。

把压缩包上传到服务器并解压到 `/opt/taskhub`：

```bash
mkdir -p /opt/taskhub
tar -xzf taskhub-source-1.0.0.tar.gz -C /opt/taskhub
cd /opt/taskhub
```

## 3. 服务器联网下载清单

只下载以下内容：

| 内容 | 固定版本或地址 |
| --- | --- |
| PHP Docker 镜像 | `php:8.5.13-fpm-trixie` |
| Nginx Docker 镜像 | `nginx:1.30.7-alpine3.24` |
| Redis PHP 扩展源码 | `https://pecl.php.net/get/redis-6.3.0.tgz` |

不再下载 Node.js、Composer、npm 依赖、PHP Composer 依赖或字体。

服务器必须已经具备 Docker Engine、Docker Compose 和 Docker Buildx。

联网期间执行：

```bash
cd /opt/taskhub
bash deploy/download-package.sh
```

脚本只拉取或下载上述内容，然后生成：

```text
deploy/artifacts/taskhub-base-images-1.0.0-x86_64.tar
deploy/artifacts/taskhub-base-images-1.0.0-x86_64.tar.sha256
deploy/cache/redis-6.3.0.tgz
```

脚本不会构建或启动 TaskHub。三个文件全部存在后即可断网。

## 4. 断网后构建镜像

执行：

```bash
cd /opt/taskhub
bash deploy/offline-install.sh
```

脚本会：

1. 检查 `vendor/`、`public/build/` 和 Redis 扩展。
2. 校验并加载 PHP、Nginx 基础镜像。
3. 完全离线构建 `taskhub-app:1.0.0` 和 `taskhub-web:1.0.0`。

脚本不会启动容器。

## 5. 后续启动

准备 `.env.production` 后执行：

```bash
TASKHUB_VERSION=1.0.0 \
TASKHUB_ENV_FILE=.env.production \
docker compose up -d --no-build --pull never
```

默认访问端口为 `18080`。
