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

# Aguardar PostgreSQL
wait_for_postgres

# Limpar cache
echo "🧹 Limpando cache..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

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

# Executar migrations do banco central (tenants)
echo "📦 Executando migrations do banco central..."
php artisan migrate --force || {
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
echo "🚀 Iniciando servidor Laravel..."

# Iniciar servidor
exec php artisan serve --host=0.0.0.0 --port=8000

