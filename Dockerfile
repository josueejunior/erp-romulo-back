FROM php:8.3-fpm

LABEL maintainer="ERP Licitações"

# ---------------------------------------------------------
# Dependências de sistema + extensões PHP (PostgreSQL etc.)
# ---------------------------------------------------------
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install \
       pdo_pgsql \
       pgsql \
       intl \
       mbstring \
       zip \
       bcmath \
       opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------
# Composer
# ---------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------
# Código da aplicação
# ---------------------------------------------------------
WORKDIR /var/www/html

COPY . /var/www/html

# ---------------------------------------------------------
# Permissões (simples, para dev)
# ---------------------------------------------------------
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# ---------------------------------------------------------
# Instala dependências PHP
# ---------------------------------------------------------
RUN composer install --no-interaction --prefer-dist --no-dev \
    && php artisan config:clear || true

# ---------------------------------------------------------
# Variáveis de ambiente padrão (podem ser sobrescritas no compose)
# ---------------------------------------------------------
ENV DB_CONNECTION=pgsql \
    DB_HOST=172.22.0.2 \
    DB_PORT=5434 \
    DB_DATABASE=erp_licitacoes \
    DB_USERNAME=erp_user \
    DB_PASSWORD=erp123 \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=http://localhost:8000

# ---------------------------------------------------------
# Porta e comando (servidor embutido do Laravel, para dev)
# Em produção prefira usar php-fpm com nginx.
# ---------------------------------------------------------
EXPOSE 8000

CMD php artisan migrate --force && php artisan tenants:migrate --force || true && \
    php artisan serve --host=0.0.0.0 --port=8000


