#!/bin/bash

echo "🧹 Limpando cache do Laravel..."

# Limpar cache de rotas
php artisan route:clear
echo "✅ Cache de rotas limpo"

# Limpar cache de configuração
php artisan config:clear
echo "✅ Cache de configuração limpo"

# Limpar cache geral
php artisan cache:clear
echo "✅ Cache geral limpo"

# Limpar cache de views (se houver)
php artisan view:clear
echo "✅ Cache de views limpo"

echo ""
echo "✅ Cache limpo com sucesso!"
echo ""
echo "📋 Verificando rotas de contratos:"
php artisan route:list --path=contratos



