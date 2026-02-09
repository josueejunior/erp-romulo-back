#!/bin/bash
set -e

echo "🚀 Iniciando aplicação ERP Licitações..."

# Verificar e instalar dependências do Composer
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "📦 Instalando dependências do Composer..."
    if [ -f "composer.json" ]; then
        composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
        echo "✅ Dependências instaladas!"
    else
        echo "❌ Erro: arquivo composer.json não encontrado!"
        exit 1
    fi
else
    echo "✅ Dependências já instaladas"
    # Verificar se predis está instalado (pode ter sido adicionado depois)
    if ! composer show predis/predis >/dev/null 2>&1; then
        echo "📦 Instalando predis/predis..."
        composer require predis/predis --no-interaction --prefer-dist --optimize-autoloader
        echo "✅ predis/predis instalado!"
    fi
fi

# Função para aguardar PostgreSQL estar pronto
wait_for_postgres() {
    echo "⏳ Aguardando PostgreSQL estar disponível..."
    until PGPASSWORD="${DB_PASSWORD}" psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "postgres" -c '\q' 2>/dev/null; do
        echo "PostgreSQL não está pronto ainda. Aguardando..."
        sleep 2
    done
    echo "✅ PostgreSQL está pronto!"
}

# Função para aguardar Redis estar pronto
wait_for_redis() {
    if [ -z "${REDIS_HOST}" ] || [ "${CACHE_STORE}" != "redis" ]; then
        echo "⏭️  Redis não configurado ou não sendo usado, pulando verificação..."
        return 0
    fi
    
    echo "⏳ Aguardando Redis estar disponível..."
    REDIS_HOST_CHECK="${REDIS_HOST:-redis}"
    REDIS_PORT_CHECK="${REDIS_PORT:-6379}"
    
    # Tentar conectar via nc (netcat) ou timeout com bash
    until (timeout 1 bash -c "cat < /dev/null > /dev/tcp/${REDIS_HOST_CHECK}/${REDIS_PORT_CHECK}" 2>/dev/null) || \
          (command -v nc >/dev/null 2>&1 && nc -z "${REDIS_HOST_CHECK}" "${REDIS_PORT_CHECK}" 2>/dev/null); do
        echo "Redis não está pronto ainda. Aguardando..."
        sleep 2
    done
    echo "✅ Redis está pronto!"
}

# Aguardar PostgreSQL
wait_for_postgres

# Aguardar Redis (se configurado)
wait_for_redis

# Limpar cache
echo "🧹 Limpando cache..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# IMPORTANTE: Limpar cache de rotas após qualquer mudança nas rotas
echo "🔄 Atualizando cache de rotas..."
php artisan route:cache || php artisan route:clear || true

# Gerar chave da aplicação se não existir
if [ ! -f .env ]; then
    echo "📝 Criando arquivo .env..."
    cp .env.example .env || true
fi

# Verificar se APP_KEY existe e está preenchido
if [ -f .env ]; then
    if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
        echo "🔑 Gerando chave da aplicação..."
        php artisan key:generate --force || true
    fi
else
    echo "🔑 Gerando chave da aplicação..."
    php artisan key:generate --force || true
fi

# Executar apenas migrations do banco central (não roda tabelas de tenant)
echo "📦 Executando migrations do banco central (migrate:central)..."
php artisan migrate:central --force || {
    echo "⚠️  Aviso: Erro ao executar migrations do banco central (pode ser normal se já executado)"
}

# Executar migrations dos tenants
echo "📦 Executando migrations dos tenants..."
php artisan tenants:migrate --force || {
    echo "⚠️  Aviso: Erro ao executar migrations dos tenants (pode ser normal se já executado)"
}

# Executar seeds apenas se a variável RUN_SEEDS estiver definida
if [ "${RUN_SEEDS:-true}" = "true" ]; then
    echo "🌱 Executando seeds..."
    php artisan db:seed --force --class=DatabaseSeeder || {
        echo "⚠️  Aviso: Erro ao executar seeds (pode ser normal se já executado)"
    }
else
    echo "⏭️  Seeds ignorados (RUN_SEEDS=false)"
fi

echo "✅ Inicialização concluída!"

# Iniciar cron em background
echo "⏰ Iniciando cron jobs..."
cron

echo "📋 Cron jobs configurados:"
echo "   - Verificar pagamentos pendentes: A cada 2 horas"
echo "   - Verificar assinaturas expiradas: Diariamente às 2h"
echo "   - Verificar documentos vencendo: Diariamente às 6h"
echo "   - Cleanup de documentos: Diariamente às 3h30"

# Mostrar logs do cron em background (opcional, para debug)
tail -f /var/log/cron.log &
CRON_LOG_PID=$!

echo "🚀 Iniciando servidor Laravel..."

# Iniciar servidor (mantém o container rodando)
exec php artisan serve --host=0.0.0.0 --port=8000

