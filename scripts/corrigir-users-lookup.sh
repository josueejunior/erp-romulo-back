#!/bin/bash

# Script para corrigir a tabela users_lookup
# Execute no servidor: bash scripts/corrigir-users-lookup.sh

echo "🔧 CORREÇÃO DA TABELA users_lookup"
echo "=================================="
echo ""

# Verificar se está no diretório correto
if [ ! -f "artisan" ]; then
    echo "❌ Erro: Execute este script do diretório raiz do projeto Laravel"
    exit 1
fi

echo "1️⃣  Verificando tabela users_lookup..."
php artisan tinker --execute="
    \$total = \App\Models\UserLookup::whereNull('deleted_at')->count();
    \$ativos = \App\Models\UserLookup::whereNull('deleted_at')->where('status', 'ativo')->count();
    echo \"   Total de registros: \$total\n\";
    echo \"   Registros ativos: \$ativos\n\";
"

echo ""
echo "2️⃣  Executando comando para popular tabela..."
php artisan users:popular-lookup --force

echo ""
echo "3️⃣  Verificando resultado..."
php artisan tinker --execute="
    \$total = \App\Models\UserLookup::whereNull('deleted_at')->count();
    \$ativos = \App\Models\UserLookup::whereNull('deleted_at')->where('status', 'ativo')->count();
    echo \"   Total de registros: \$total\n\";
    echo \"   Registros ativos: \$ativos\n\";
"

echo ""
echo "✅ Correção concluída!"
echo ""
echo "📝 Próximos passos:"
echo "   1. Acesse https://gestor.addsimp.com/admin/usuarios"
echo "   2. Verifique se os usuários aparecem na listagem"
echo "   3. Se ainda não aparecer, verifique os logs: tail -f storage/logs/laravel.log"

