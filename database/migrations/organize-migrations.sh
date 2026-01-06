#!/bin/bash

# Script para organizar migrations em módulos
# Execute no WSL: bash organize-migrations.sh

cd "$(dirname "$0")"

# Criar diretórios
mkdir -p Auth Empresa Tenancy Permission System Assinatura

# Mover migrations de Auth
mv 0001_01_01_000000_create_users_table.php Auth/ 2>/dev/null
mv 2025_01_22_000001_create_admin_users_table.php Auth/ 2>/dev/null
mv 0001_01_01_000001_create_password_reset_tokens_table.php Auth/ 2>/dev/null
mv 0001_01_01_000002_create_sessions_table.php Auth/ 2>/dev/null

# Mover migrations de Empresa
mv 2025_12_13_163303_create_empresas_table.php Empresa/ 2>/dev/null
mv 2025_12_13_163320_create_empresa_user_table.php Empresa/ 2>/dev/null

# Mover migrations de Tenancy
mv 2019_09_15_000010_create_tenants_table.php Tenancy/ 2>/dev/null
mv 2019_09_15_000020_create_domains_table.php Tenancy/ 2>/dev/null

# Mover migrations de Permission
mv 2025_12_13_163253_create_permissions_table.php Permission/ 2>/dev/null
mv 2025_12_13_163254_create_roles_table.php Permission/ 2>/dev/null
mv 2025_12_13_163255_create_model_has_permissions_table.php Permission/ 2>/dev/null
mv 2025_12_13_163256_create_model_has_roles_table.php Permission/ 2>/dev/null
mv 2025_12_13_163257_create_role_has_permissions_table.php Permission/ 2>/dev/null

# Mover migrations de System
mv 0001_01_01_000001_create_cache_table.php System/ 2>/dev/null
mv 0001_01_01_000001_create_cache_locks_table.php System/ 2>/dev/null
mv 0001_01_01_000002_create_jobs_table.php System/ 2>/dev/null
mv 0001_01_01_000002_create_job_batches_table.php System/ 2>/dev/null
mv 0001_01_01_000002_create_failed_jobs_table.php System/ 2>/dev/null
mv 2025_12_13_212348_create_personal_access_tokens_table.php System/ 2>/dev/null

# Mover migrations de Assinatura
mv 2025_12_19_000001_create_planos_table.php Assinatura/ 2>/dev/null
mv 2025_12_19_000002_create_assinaturas_table.php Assinatura/ 2>/dev/null

echo "✅ Migrations organizadas com sucesso!"
echo ""
echo "Estrutura criada:"
echo "  Auth/ - Autenticação e usuários"
echo "  Empresa/ - Empresas e relacionamentos"
echo "  Tenancy/ - Multi-tenancy"
echo "  Permission/ - Permissões e roles"
echo "  System/ - Sistema (cache, jobs, tokens)"
echo "  Assinatura/ - Planos e assinaturas"






