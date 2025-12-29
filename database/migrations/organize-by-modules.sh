#!/bin/bash

# Script para organizar migrations seguindo arquitetura de módulos
# Estrutura: Legacy/Modules/, Modules/, tenant/, System/

cd "$(dirname "$0")"

# Criar estrutura de diretórios
mkdir -p Legacy/Modules/{Empresa,Processo,Orcamento,Contrato,Fornecedor,Orgao,Documento,Empenho,NotaFiscal,AutorizacaoFornecimento,Custo,Auditoria}
mkdir -p Modules/{Auth,Empresa,Assinatura}
mkdir -p System/{Cache,Jobs,Tokens}
mkdir -p Tenancy

echo "📁 Estrutura de diretórios criada"

# ============================================
# MIGRATIONS DO SISTEMA BASE
# ============================================

# System - Cache
mv System/0001_01_01_000001_create_cache_table.php System/Cache/ 2>/dev/null
mv System/0001_01_01_000001_create_cache_locks_table.php System/Cache/ 2>/dev/null

# System - Jobs
mv System/0001_01_01_000002_create_jobs_table.php System/Jobs/ 2>/dev/null
mv System/0001_01_01_000002_create_job_batches_table.php System/Jobs/ 2>/dev/null
mv System/0001_01_01_000002_create_failed_jobs_table.php System/Jobs/ 2>/dev/null

# System - Tokens
mv System/2025_12_13_212348_create_personal_access_tokens_table.php System/Tokens/ 2>/dev/null

# Tenancy
mv Tenancy/2019_09_15_000010_create_tenants_table.php Tenancy/ 2>/dev/null
mv Tenancy/2019_09_15_000020_create_domains_table.php Tenancy/ 2>/dev/null

# Permission (sistema base)
mv Permission/*.php System/Permission/ 2>/dev/null
rmdir Permission 2>/dev/null

# ============================================
# MIGRATIONS DE MÓDULOS LEGACY
# ============================================

# Empresa (Legacy)
mv Empresa/2025_12_13_163303_create_empresas_table.php Legacy/Modules/Empresa/ 2>/dev/null
mv Empresa/2025_12_13_163320_create_empresa_user_table.php Legacy/Modules/Empresa/ 2>/dev/null
rmdir Empresa 2>/dev/null

# Processo (Legacy - mover de tenant)
mv tenant/Processo/*.php Legacy/Modules/Processo/ 2>/dev/null
rmdir tenant/Processo 2>/dev/null

# Orcamento (Legacy - mover de tenant)
mv tenant/Orcamento/*.php Legacy/Modules/Orcamento/ 2>/dev/null
rmdir tenant/Orcamento 2>/dev/null

# Contrato (Legacy - mover de tenant)
mv tenant/Contrato/*.php Legacy/Modules/Contrato/ 2>/dev/null
rmdir tenant/Contrato 2>/dev/null

# Fornecedor (Legacy - mover de tenant)
mv tenant/Fornecedor/*.php Legacy/Modules/Fornecedor/ 2>/dev/null
rmdir tenant/Fornecedor 2>/dev/null

# Orgao (Legacy - mover de tenant)
mv tenant/Orgao/*.php Legacy/Modules/Orgao/ 2>/dev/null
rmdir tenant/Orgao 2>/dev/null

# Documento (Legacy - mover de tenant)
mv tenant/Documento/*.php Legacy/Modules/Documento/ 2>/dev/null
rmdir tenant/Documento 2>/dev/null

# Empenho (Legacy - mover de tenant)
mv tenant/Empenho/*.php Legacy/Modules/Empenho/ 2>/dev/null
rmdir tenant/Empenho 2>/dev/null

# NotaFiscal (Legacy - mover de tenant)
mv tenant/NotaFiscal/*.php Legacy/Modules/NotaFiscal/ 2>/dev/null
rmdir tenant/NotaFiscal 2>/dev/null

# AutorizacaoFornecimento (Legacy - mover de tenant)
mv tenant/AutorizacaoFornecimento/*.php Legacy/Modules/AutorizacaoFornecimento/ 2>/dev/null
rmdir tenant/AutorizacaoFornecimento 2>/dev/null

# Custo (Legacy - mover de tenant)
mv tenant/Custo/*.php Legacy/Modules/Custo/ 2>/dev/null
rmdir tenant/Custo 2>/dev/null

# Auditoria (Legacy - mover de tenant)
mv tenant/Auditoria/*.php Legacy/Modules/Auditoria/ 2>/dev/null
rmdir tenant/Auditoria 2>/dev/null

# Remover diretório tenant vazio se existir
rmdir tenant 2>/dev/null

# ============================================
# MIGRATIONS DE MÓDULOS DDD
# ============================================

# Auth (DDD)
mv Auth/*.php Modules/Auth/ 2>/dev/null
rmdir Auth 2>/dev/null

# Assinatura (DDD)
mv Assinatura/*.php Modules/Assinatura/ 2>/dev/null
rmdir Assinatura 2>/dev/null

echo ""
echo "✅ Migrations organizadas seguindo arquitetura de módulos!"
echo ""
echo "📂 Estrutura final:"
echo "  Legacy/Modules/     - Módulos Legacy (Processo, Orcamento, etc.)"
echo "  Modules/            - Módulos DDD (Auth, Assinatura, etc.)"
echo "  System/             - Sistema base (Cache, Jobs, Tokens, Permission)"
echo "  Tenancy/            - Multi-tenancy"
echo ""
echo "📋 Módulos Legacy organizados:"
echo "  - Empresa, Processo, Orcamento, Contrato"
echo "  - Fornecedor, Orgao, Documento"
echo "  - Empenho, NotaFiscal, AutorizacaoFornecimento"
echo "  - Custo, Auditoria"
echo ""
echo "📋 Módulos DDD organizados:"
echo "  - Auth, Assinatura"




