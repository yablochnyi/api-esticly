FROM php:8.4-fpm-bookworm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    default-mysql-client \
    netcat-openbsd \
    nginx \
    awscli \
    supervisor \
  && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    zip \
    intl \
    bcmath \
    pcntl \
  && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application
COPY . .

# Ensure storage cache dirs exist for package discovery (view compiled path needs realpath).
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

# Install PHP deps (Filament/Horizon included). Prefer git sources to avoid flaky GitHub codeload zip downloads.
RUN composer install --no-dev --no-interaction --prefer-source --optimize-autoloader

# Supervisor + entrypoint
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/mysql-backup-supervisor.conf /etc/supervisor/conf.d/mysql-backup.conf
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/mysql-backup.sh /usr/local/bin/mysql-backup.sh
RUN chmod +x /entrypoint.sh \
  && chmod +x /usr/local/bin/mysql-backup.sh \
  && mkdir -p /tmp/nginx/client_body /tmp/nginx/proxy /tmp/nginx/fastcgi /tmp/nginx/uwsgi /tmp/nginx/scgi \
  && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
