#!/bin/bash

# Script para organizar migrations apenas por módulos funcionais
# Sem separação Legacy/DDD - apenas organização por domínio

cd "$(dirname "$0")"

# Criar estrutura de diretórios por módulos funcionais
mkdir -p Modules/{Auth,Empresa,Processo,Orcamento,Contrato,Fornecedor,Orgao,Documento,Empenho,NotaFiscal,AutorizacaoFornecimento,Custo,Auditoria,Assinatura}
mkdir -p System/{Cache,Jobs,Tokens,Permission}
mkdir -p Tenancy

echo "📁 Estrutura de diretórios criada"

# ============================================
# MIGRATIONS DE MÓDULOS FUNCIONAIS
# ============================================

# Auth
if [ -d "Legacy/Modules" ]; then
    # Se existe Legacy, mover de lá
    mv Legacy/Modules/* Modules/ 2>/dev/null
    rmdir -p Legacy/Modules 2>/dev/null
fi

# Mover de Modules/Auth se existir
if [ -d "Modules/Auth" ]; then
    # Já está no lugar certo
    :
fi

# Mover de Legacy/Modules/Empresa se existir
if [ -d "Legacy/Modules/Empresa" ]; then
    mv Legacy/Modules/Empresa/* Modules/Empresa/ 2>/dev/null
    rmdir Legacy/Modules/Empresa 2>/dev/null
fi

# Mover todos os módulos de Legacy/Modules para Modules
if [ -d "Legacy/Modules" ]; then
    for dir in Legacy/Modules/*/; do
        if [ -d "$dir" ]; then
            module_name=$(basename "$dir")
            mv "$dir"* Modules/"$module_name"/ 2>/dev/null
            rmdir "$dir" 2>/dev/null
        fi
    done
    rmdir Legacy/Modules 2>/dev/null
    rmdir Legacy 2>/dev/null
fi

# Assinatura
if [ -d "Modules/Assinatura" ]; then
    # Já está no lugar certo
    :
fi

# ============================================
# MIGRATIONS DO SISTEMA
# ============================================

# System já está organizado, apenas garantir estrutura
if [ -d "System" ]; then
    # Cache
    if [ ! -d "System/Cache" ]; then
        mkdir -p System/Cache
        mv System/0001_01_01_000001_create_cache*.php System/Cache/ 2>/dev/null
    fi
    
    # Jobs
    if [ ! -d "System/Jobs" ]; then
        mkdir -p System/Jobs
        mv System/0001_01_01_000002_create_job*.php System/Jobs/ 2>/dev/null
    fi
    
    # Tokens
    if [ ! -d "System/Tokens" ]; then
        mkdir -p System/Tokens
        mv System/*token*.php System/Tokens/ 2>/dev/null
    fi
    
    # Permission
    if [ -d "System/Permission" ]; then
        # Já está no lugar certo
        :
    fi
fi

echo ""
echo "✅ Migrations organizadas por módulos funcionais!"
echo ""
echo "📂 Estrutura final:"
echo "  Modules/            - Módulos funcionais (Auth, Empresa, Processo, etc.)"
echo "  System/             - Sistema base (Cache, Jobs, Tokens, Permission)"
echo "  Tenancy/            - Multi-tenancy"
echo ""
echo "📋 Módulos organizados:"
echo "  - Auth, Empresa, Processo, Orcamento, Contrato"
echo "  - Fornecedor, Orgao, Documento"
echo "  - Empenho, NotaFiscal, AutorizacaoFornecimento"
echo "  - Custo, Auditoria, Assinatura"





