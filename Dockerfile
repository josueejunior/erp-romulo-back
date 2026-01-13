FROM php:8.3-fpm

LABEL maintainer="ERP Licitações"

# ---------------------------------------------------------
# Dependências de sistema + extensões PHP (PostgreSQL etc.)
# ---------------------------------------------------------
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    postgresql-client \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    cron \
    && docker-php-ext-install \
       pdo_pgsql \
       pgsql \
       intl \
       mbstring \
       zip \
       bcmath \
       opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# postgresql-client inclui pg_dump necessário para backups de banco de dados

# ---------------------------------------------------------
# Composer
# ---------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------
# Código da aplicação
# ---------------------------------------------------------
WORKDIR /var/www/html

# Copiar arquivos do Composer primeiro (para cache de layers)
COPY composer.json composer.lock* ./

# Instalar dependências (será sobrescrito pelo volume, mas útil para build)
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader --no-scripts || true

# Copiar resto dos arquivos
COPY . /var/www/html

# ---------------------------------------------------------
# Script de inicialização
# ---------------------------------------------------------
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# ---------------------------------------------------------
# Configuração do Cron
# ---------------------------------------------------------
# Copiar script wrapper para cron
COPY docker/laravel-cron.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/laravel-cron.sh

# Copiar crontab (formato /etc/cron.d/)
COPY docker/crontab /etc/cron.d/laravel-cron
RUN chmod 0644 /etc/cron.d/laravel-cron

# Criar log do cron
RUN touch /var/log/cron.log && chmod 666 /var/log/cron.log

# ---------------------------------------------------------
# Permissões (simples, para dev)
# ---------------------------------------------------------
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# ---------------------------------------------------------
# Variáveis de ambiente padrão (podem ser sobrescritas no compose)
# ---------------------------------------------------------
ENV DB_CONNECTION=pgsql \
    DB_HOST=postgres \
    DB_PORT=5432 \
    DB_DATABASE=erp_licitacoes \
    DB_USERNAME=erp_user \
    DB_PASSWORD=erp123 \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=http://localhost:8000 \
    RUN_SEEDS=true

# ---------------------------------------------------------
# Porta e comando (servidor embutido do Laravel, para dev)
# Em produção prefira usar php-fpm com nginx.
# ---------------------------------------------------------
EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]


