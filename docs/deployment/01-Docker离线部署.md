# TaskHub Docker 离线部署

## 1. 开发机准备

在项目根目录执行：

```bash
composer install --no-dev --optimize-autoloader
npm run build
```

确认以下文件存在：

```text
vendor/autoload.php
public/build/manifest.json
```

然后把完整项目上传到服务器。`vendor/`、`public/build/`、隐藏文件 `.dockerignore` 必须一起上传；真实 `.env` 不要上传。

## 2. 准备 Redis 扩展

使用可联网电脑的浏览器下载：

```text
https://pecl.php.net/get/redis-6.3.0.tgz
```

上传到服务器项目目录：

```text
/opt/taskhub/deploy/cache/redis-6.3.0.tgz
```

文件 SHA-256 应为：

```text
0d5141f634bd1db6c1ddcda053d25ecf2c4fc1c395430d534fd3f8d51dd7f0b5
```

## 3. 服务器联网拉取镜像

进入服务器项目目录，手工拉取两个镜像：

```bash
cd /opt/taskhub
docker pull php:8.5.13-fpm-trixie
docker pull nginx:1.30.7-alpine3.24
```

两条命令都成功后即可断开公网，不需要执行其他下载。

## 4. 断网后构建

执行：

```bash
cd /opt/taskhub
bash deploy/offline-install.sh
```

脚本使用本地项目、`vendor/`、`public/build/`、两个基础镜像和 Redis 扩展，离线生成：

```text
taskhub-app:1.0.0
taskhub-web:1.0.0
```

脚本不会启动容器。

## 5. 配置

复制生产配置：

```bash
cp .env.production.example .env.production
```

填写 `APP_KEY`、数据库、Redis、SSO、外部接口和邮件配置。生产密钥只保存在服务器，不提交到 Git。

数据库为空时，只执行一次：

```bash
mysql -h 数据库地址 -u 用户名 -p 数据库名 < database/schema.sql
```

## 6. 启动

执行：

```bash
TASKHUB_VERSION=1.0.0 \
TASKHUB_ENV_FILE=.env.production \
docker compose up -d --no-build --pull never
```

查看状态：

```bash
docker compose ps
```

默认访问地址：

```text
http://服务器IP:18080
```
