# TaskHub Docker 离线部署

## 1. 目标

服务器临时联网时生成一个完整离线镜像包；断网后只加载该镜像包。

整个流程只使用两个脚本：

```text
deploy/download-package.sh  联网下载、构建并导出离线包
deploy/offline-install.sh   断网校验并加载离线包
```

这两个脚本都不会启动 TaskHub。

## 2. 前置条件

服务器架构必须是 `x86_64`，并且已经安装：

```text
Docker Engine
Docker Compose
Docker Buildx
```

MySQL 和 Redis 使用现有远程服务，不下载对应镜像。宿主机不需要安装 PHP、Composer、Node.js、npm 或 Nginx。

## 3. 联网下载

进入项目目录：

```bash
cd /opt/taskhub
```

执行唯一的联网脚本：

```bash
bash deploy/download-package.sh
```

脚本会完成：

1. 拉取 PHP、Node.js、Composer、Nginx 四个固定版本的基础镜像。
2. 在 Docker 构建过程中下载 Composer 依赖、npm 依赖、Redis PHP 扩展和字体。
3. 生成 `taskhub-app:1.0.0` 和 `taskhub-web:1.0.0`。
4. 将两个最终镜像导出为离线包并生成 SHA-256 校验文件。

脚本不会创建或启动容器。

生成文件：

```text
deploy/artifacts/taskhub-1.0.0-x86_64.tar
deploy/artifacts/taskhub-1.0.0-x86_64.tar.sha256
```

只有看到以上两个文件后，才可以关闭公网。

## 4. 离线安装

断网后，在项目根目录执行：

```bash
bash deploy/offline-install.sh
```

脚本只执行两项操作：

1. 校验离线镜像包是否完整。
2. 使用 `docker image load` 加载两个 TaskHub 镜像。

脚本不会启动 TaskHub。

## 5. 后续启动

需要正式运行系统时，先准备 `.env.production`，再执行：

```bash
TASKHUB_VERSION=1.0.0 \
TASKHUB_ENV_FILE=.env.production \
docker compose up -d --no-build --pull never
```

默认访问端口为 `18080`。此步骤不属于下载或离线安装脚本。

## 6. 文件职责

```text
Dockerfile                              构建应用和 Web 镜像
compose.yaml                            定义应用、定时任务和 Web 容器
deploy/download-package.sh              生成离线包
deploy/offline-install.sh               加载离线包
docker/nginx/default.conf               Nginx 配置
docker/php/                             PHP 配置
docker/scripts/container-entrypoint.sh  容器入口
```
