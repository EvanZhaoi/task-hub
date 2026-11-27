# 所有外部镜像都固定到明确版本，离线构建时只使用已经下载到服务器的镜像。
ARG PHP_IMAGE=php:8.5.13-fpm-trixie
ARG NGINX_IMAGE=nginx:1.30.7-alpine3.24

# PHP 运行阶段安装 TaskHub 真正需要的扩展。
FROM ${PHP_IMAGE} AS php-runtime

ARG REDIS_EXTENSION_SHA256=0d5141f634bd1db6c1ddcda053d25ecf2c4fc1c395430d534fd3f8d51dd7f0b5

# Redis 扩展由联网下载脚本提前保存，离线构建时不再访问 PECL。
COPY deploy/cache/redis-6.3.0.tgz /tmp/redis-6.3.0.tgz

RUN set -eux; \
    docker-php-ext-install -j"$(nproc)" \
        opcache \
        pdo_mysql; \
    echo "${REDIS_EXTENSION_SHA256}  /tmp/redis-6.3.0.tgz" | sha256sum --check; \
    yes '' | pecl install /tmp/redis-6.3.0.tgz; \
    docker-php-ext-enable redis; \
    apt-get purge -y --auto-remove ${PHPIZE_DEPS}; \
    rm -rf /var/lib/apt/lists/* /tmp/pear ~/.pearrc

COPY docker/php/taskhub.ini /usr/local/etc/php/conf.d/zz-taskhub.ini
COPY docker/php/taskhub-fpm.conf /usr/local/etc/php-fpm.d/zz-taskhub.conf

WORKDIR /var/www/html

# 最终 PHP 应用镜像直接使用开发机生成的 vendor 和 public/build。
FROM php-runtime AS app

COPY . .

RUN test -f vendor/autoload.php \
    && test -f public/build/manifest.json \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

COPY docker/scripts/container-entrypoint.sh /usr/local/bin/taskhub-entrypoint

RUN chmod +x /usr/local/bin/taskhub-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["taskhub-entrypoint"]
CMD ["php-fpm", "-F"]

# Nginx 镜像只保存公开静态文件和反向代理配置，不包含 PHP 源码与密钥。
FROM ${NGINX_IMAGE} AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1/up || exit 1
