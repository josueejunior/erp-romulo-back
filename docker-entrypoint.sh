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

# 🔥 GARANTIR: Executar migrations do banco central (master)
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📦 EXECUTANDO MIGRATIONS DO BANCO CENTRAL (MASTER)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Verificar status antes
echo "🔍 Verificando migrations pendentes do banco central..."
php artisan migrate:central --status 2>&1 || echo "⚠️  Nenhuma migration encontrada ou erro ao verificar status"

# Executar migrations com retry (incluindo seeds se RUN_SEEDS=true)
echo ""
echo "🚀 Executando migrations do banco central..."
MIGRATION_SUCCESS=false
SEED_OPTION=""
if [ "${RUN_SEEDS:-true}" = "true" ]; then
    SEED_OPTION="--seed"
    echo "   🌱 Seeds serão executados após as migrations (RUN_SEEDS=true)"
else
    echo "   ⏭️  Seeds serão ignorados (RUN_SEEDS=false)"
fi

for i in 1 2 3 4 5; do
    echo "   Tentativa $i de 5..."
    if php artisan migrate:central --force $SEED_OPTION 2>&1; then
        echo "   ✅ Migrations do central executadas com sucesso!"
        MIGRATION_SUCCESS=true
        break
    else
        if [ "$i" -eq 5 ]; then
            echo "   ❌ Todas as tentativas falharam!"
            echo ""
            echo "⚠️  AÇÃO NECESSÁRIA: Execute manualmente:"
            echo "   docker exec erp-licitacoes-app php artisan migrate:central --force $SEED_OPTION"
            echo ""
            echo "   Ou execute migrations individuais:"
            echo "   docker exec erp-licitacoes-app php artisan migrate --path=database/migrations/central/tenancy --force"
            echo "   docker exec erp-licitacoes-app php artisan migrate --path=database/migrations/central/usuarios --force"
            echo "   docker exec erp-licitacoes-app php artisan migrate --path=database/migrations/central/planos --force"
            echo "   docker exec erp-licitacoes-app php artisan migrate --path=database/migrations/central --force"
            if [ "${RUN_SEEDS:-true}" = "true" ]; then
                echo "   docker exec erp-licitacoes-app php artisan db:seed --force"
            fi
        else
            echo "   ⏳ Aguardando 3 segundos antes da próxima tentativa..."
            sleep 3
        fi
    fi
done

# Verificar status final
echo ""
echo "🔍 Verificando status final das migrations do banco central..."
php artisan migrate:central --status 2>&1 || echo "⚠️  Erro ao verificar status final"

if [ "$MIGRATION_SUCCESS" = true ]; then
    echo "✅ Migrations do banco central concluídas com sucesso!"
else
    echo "⚠️  ATENÇÃO: Algumas migrations podem não ter sido executadas!"
fi
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Executar migrations dos tenants
echo "📦 Executando migrations dos tenants..."
php artisan tenants:migrate --force || {
    echo "⚠️  Aviso: Erro ao executar migrations dos tenants (pode ser normal se já executado)"
}

# 🔥 NOTA: Seeds do banco central já foram executados pelo comando migrate:central --seed acima
# Se precisar executar seeds adicionais ou específicos, adicione aqui
if [ "${RUN_SEEDS:-true}" = "true" ]; then
    echo "✅ Seeds do banco central já foram executados pelo migrate:central --seed"
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

