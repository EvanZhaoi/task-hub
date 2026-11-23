# 所有外部镜像都固定到明确版本，联网构建完成后不再依赖浮动的 latest 标签。
ARG PHP_IMAGE=php:8.5.13-fpm-trixie
ARG NODE_IMAGE=node:24.22.3-alpine3.24
ARG COMPOSER_IMAGE=composer:2.11.2
ARG NGINX_IMAGE=nginx:1.30.7-alpine3.24

# Composer 镜像只提供经过官方打包的 Composer 可执行文件。
FROM ${COMPOSER_IMAGE} AS composer-binary

# 前端阶段安装锁文件中的全部 npm 依赖，并生成可直接交给 Nginx 的静态文件。
FROM ${NODE_IMAGE} AS frontend-build
WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY resources ./resources
COPY public ./public
COPY components.json tsconfig.json vite.config.ts ./

# typecheck 先阻止类型错误进入镜像，build 会同时下载并打包当前配置的 Bunny 字体。
RUN npm run typecheck \
    && npm run build

# PHP 运行阶段安装 TaskHub 真正需要的扩展。
FROM ${PHP_IMAGE} AS php-runtime

ARG REDIS_EXTENSION_VERSION=6.3.0

RUN set -eux; \
    docker-php-ext-install -j"$(nproc)" \
        opcache \
        pdo_mysql; \
    yes '' | pecl install "redis-${REDIS_EXTENSION_VERSION}"; \
    docker-php-ext-enable redis; \
    apt-get purge -y --auto-remove ${PHPIZE_DEPS}; \
    rm -rf /var/lib/apt/lists/* /tmp/pear ~/.pearrc

COPY docker/php/taskhub.ini /usr/local/etc/php/conf.d/zz-taskhub.ini
COPY docker/php/taskhub-fpm.conf /usr/local/etc/php-fpm.d/zz-taskhub.conf

WORKDIR /var/www/html

# 最终 PHP 应用镜像包含完整代码、生产 Composer 依赖和前端构建结果。
FROM php-runtime AS app

COPY --from=composer-binary /usr/bin/composer /usr/local/bin/composer
COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
    && composer check-platform-reqs --no-dev \
    && rm -rf public/build \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

COPY --from=frontend-build /app/public/build ./public/build
COPY docker/scripts/container-entrypoint.sh /usr/local/bin/taskhub-entrypoint

RUN chmod +x /usr/local/bin/taskhub-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["taskhub-entrypoint"]
CMD ["php-fpm", "-F"]

# Nginx 镜像只保存公开静态文件和反向代理配置，不包含 PHP 源码与密钥。
FROM ${NGINX_IMAGE} AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=frontend-build /app/public/build /var/www/html/public/build

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1/up || exit 1
