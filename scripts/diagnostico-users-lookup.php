<?php

/**
 * Script de diagnóstico e correção da tabela users_lookup
 * 
 * Execute: php artisan tinker < scripts/diagnostico-users-lookup.php
 * Ou: php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); require 'scripts/diagnostico-users-lookup.php';"
 */

use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use App\Models\UserLookup;

echo "🔍 DIAGNÓSTICO DA TABELA users_lookup\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Verificar se a tabela existe
try {
    $totalLookup = UserLookup::whereNull('deleted_at')->count();
    echo "✅ Tabela users_lookup existe\n";
    echo "   Total de registros (ativos): {$totalLookup}\n\n";
} catch (\Exception $e) {
    echo "❌ ERRO: Tabela users_lookup não existe ou há problema de conexão\n";
    echo "   Erro: {$e->getMessage()}\n";
    exit(1);
}

// 2. Verificar registros por status
echo "📊 Registros por status:\n";
$statusCounts = UserLookup::whereNull('deleted_at')
    ->selectRaw('status, COUNT(*) as total')
    ->groupBy('status')
    ->get();

foreach ($statusCounts as $status) {
    echo "   - {$status->status}: {$status->total}\n";
}
echo "\n";

// 3. Verificar filtro padrão (ativo)
$ativos = UserLookup::whereNull('deleted_at')
    ->where('status', 'ativo')
    ->count();
echo "📋 Registros com status 'ativo': {$ativos}\n\n";

// 4. Verificar se há tenants
$totalTenants = Tenant::count();
echo "🏢 Total de tenants: {$totalTenants}\n\n";

// 5. Verificar se há usuários nos tenants
echo "👥 Verificando usuários nos tenants...\n";
$tenantsComUsuarios = 0;
$totalUsuarios = 0;

foreach (Tenant::all() as $tenant) {
    try {
        tenancy()->initialize($tenant);
        
        $usersCount = \App\Modules\Auth\Models\User::whereNull('excluido_em')->count();
        if ($usersCount > 0) {
            $tenantsComUsuarios++;
            $totalUsuarios += $usersCount;
            echo "   - Tenant {$tenant->id} ({$tenant->razao_social}): {$usersCount} usuários\n";
        }
        
        tenancy()->end();
    } catch (\Exception $e) {
        echo "   ⚠️  Tenant {$tenant->id}: Erro ao acessar - {$e->getMessage()}\n";
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}

echo "\n";
echo "📈 Resumo:\n";
echo "   - Tenants com usuários: {$tenantsComUsuarios}\n";
echo "   - Total de usuários: {$totalUsuarios}\n";
echo "   - Registros em users_lookup: {$totalLookup}\n\n";

// 6. Diagnóstico do problema
if ($totalLookup === 0 && $totalUsuarios > 0) {
    echo "⚠️  PROBLEMA IDENTIFICADO: Tabela users_lookup está vazia!\n";
    echo "   Solução: Execute o comando: php artisan users:popular-lookup\n\n";
} elseif ($ativos === 0 && $totalLookup > 0) {
    echo "⚠️  PROBLEMA IDENTIFICADO: Nenhum registro com status 'ativo'!\n";
    echo "   Solução: Verifique o status dos registros ou ajuste o filtro\n\n";
} elseif ($ativos < $totalUsuarios) {
    echo "⚠️  PROBLEMA IDENTIFICADO: Há {$totalUsuarios} usuários mas apenas {$ativos} registros ativos em users_lookup!\n";
    echo "   Solução: Execute o comando: php artisan users:popular-lookup --force\n\n";
} else {
    echo "✅ Tudo parece estar OK!\n";
    echo "   Se ainda não aparecer na listagem, verifique:\n";
    echo "   1. Logs do Laravel (storage/logs/laravel.log)\n";
    echo "   2. Filtros aplicados na requisição\n";
    echo "   3. Permissões do usuário admin\n\n";
}

// 7. Sugestão de correção rápida
if ($totalLookup === 0 || $ativos < $totalUsuarios) {
    echo "🔧 CORREÇÃO RÁPIDA:\n";
    echo "   Execute no servidor:\n";
    echo "   cd /caminho/do/projeto\n";
    echo "   php artisan users:popular-lookup --force\n\n";
}

echo str_repeat("=", 60) . "\n";
echo "✅ Diagnóstico concluído!\n";

