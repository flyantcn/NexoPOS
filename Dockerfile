# NexoPOS PHP 开发镜像
# 支持 amd64 (x86) 和 arm64 (ARM Armbian)
FROM php:8.4-fpm-alpine

# 替换为国内 Alpine 镜像源（清华源）
#RUN sed -i 's|dl-cdn.alpinelinux.org|mirrors.tuna.tsinghua.edu.cn|g' /etc/apk/repositories
RUN sed -i 's|dl-cdn.alpinelinux.org|mirrors.aliyun.com|g' /etc/apk/repositories

# 避免交互提示
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

# ===== 安装系统依赖 =====
RUN apk add --no-cache \
    curl \
    curl-dev \
    linux-headers \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    bzip2-dev \
    $PHPIZE_DEPS \
    mariadb-client \
    unzip \
    git

# ===== 安装 PHP 扩展 =====
# composer.json 要求: curl, gd, mbstring, intl, zip
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        curl \
        gd \
        mbstring \
        intl \
        zip \
        pdo \
        pdo_mysql \
        bcmath \
        opcache \
        pcntl \
        posix \
        sockets \
        soap

# ===== 安装 Composer =====
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ===== 安装 Node.js 24 + npm (前端构建用) =====
RUN apk add --no-cache nodejs npm

# ===== PHP 生产级配置 =====
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

# ===== 设置工作目录 =====
WORKDIR /var/www/html

# ===== 创建非 root 用户 =====
RUN addgroup -S www-data 2>/dev/null || true \
    && adduser -S www-data -G www-data 2>/dev/null || true

# ===== 暴露端口 =====
EXPOSE 9000

CMD ["php-fpm"]
