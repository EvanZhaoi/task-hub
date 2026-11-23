#!/bin/sh

set -eu

# Named Volume 第一次挂载时会复制镜像内目录；这里再次确保运行目录完整且可写。
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

# 配置缓存基于容器启动时注入的 .env.production 生成，不把生产密钥写入镜像。
php artisan config:cache --no-interaction
php artisan view:cache --no-interaction

exec "$@"
