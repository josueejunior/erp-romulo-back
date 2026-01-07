#!/bin/bash

# Script para organizar código em módulos funcionais
# Execute no WSL: bash organize-modules.sh

cd "$(dirname "$0")/app"

echo "📁 Criando estrutura de módulos..."

# Criar estrutura base de módulos
mkdir -p Modules/{Auth,Empresa,Processo,Orcamento,Contrato,Fornecedor,Orgao,Documento,Empenho,NotaFiscal,AutorizacaoFornecimento,Custo,Auditoria,Assinatura,Calendario}/{Models,Services,Controllers,Resources,Observers,Policies}

# Criar estrutura Shared
mkdir -p Shared/{Contracts,Database,Helpers,Http/{Controllers,Middleware,Resources},Services,Rules}

# Criar estrutura Admin
mkdir -p Admin/{Controllers,Middleware}

echo "✅ Estrutura de diretórios criada"
echo ""
echo "📋 Próximos passos:"
echo "  1. Mover arquivos para módulos correspondentes"
echo "  2. Atualizar namespaces"
echo "  3. Atualizar imports"
echo "  4. Atualizar rotas e service providers"







