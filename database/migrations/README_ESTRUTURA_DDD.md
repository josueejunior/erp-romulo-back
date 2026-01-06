# 📘 Estrutura de Migrations - DDD + Multi-Tenancy

## ✅ Documentação Criada

1. **`ESTRUTURA_DDD.md`** - Guia completo da estrutura ideal
2. **`REORGANIZAR_ESTRUTURA.md`** - Mapeamento de migrations atuais → nova estrutura
3. **`ANALISE_INDICES.md`** - Análise de índices faltantes e recomendações

## 🏗️ Estrutura Ideal

```
database/migrations/
├── central/                    # 🏛️ BANCO CENTRAL (shared)
│   ├── tenancy/               # Multi-tenancy
│   ├── usuarios/               # Usuários globais
│   ├── planos/                 # Planos (se global)
│   ├── cupons/                 # Cupons (se global)
│   └── system/                 # Sistema base
│       ├── cache/
│       ├── jobs/
│       ├── tokens/
│       └── permissions/
│
└── tenant/                      # 🏢 BANCO TENANT (operacional)
    ├── empresas/
    ├── assinaturas/
    ├── processos/
    ├── orcamentos/
    ├── contratos/
    ├── fornecedores/
    ├── orgaos/
    ├── documentos/
    ├── empenhos/
    ├── notas_fiscais/
    ├── autorizacoes_fornecimento/
    ├── custos/
    └── auditoria/
```

## 🚀 Próximos Passos

### 1. Criar Estrutura de Pastas

**Opção A: Via IDE/Explorador**
- Criar manualmente as pastas conforme `ESTRUTURA_DDD.md`

**Opção B: Via Terminal (Linux/Mac)**
```bash
cd erp-romulo-back/database/migrations
mkdir -p central/{tenancy,usuarios,planos,cupons,system/{cache,jobs,tokens,permissions}}
mkdir -p tenant/{empresas,assinaturas,processos,orcamentos,contratos,fornecedores,orgaos,documentos,empenhos,notas_fiscais,autorizacoes_fornecimento,custos,auditoria}
```

**Opção C: Via PowerShell (Windows)**
```powershell
# Executar no diretório erp-romulo-back/database/migrations
New-Item -ItemType Directory -Force -Path central\tenancy, central\usuarios, central\planos, central\cupons, central\system\cache, central\system\jobs, central\system\tokens, central\system\permissions
New-Item -ItemType Directory -Force -Path tenant\empresas, tenant\assinaturas, tenant\processos, tenant\orcamentos, tenant\contratos, tenant\fornecedores, tenant\orgaos, tenant\documentos, tenant\empenhos, tenant\notas_fiscais, tenant\autorizacoes_fornecimento, tenant\custos, tenant\auditoria
```

### 2. Aplicar Gradualmente

⚠️ **IMPORTANTE:** Não mover migrations já executadas em produção!

- ✅ Novas migrations seguem a nova estrutura
- ✅ Migrations antigas ficam onde estão (compatibilidade)
- ✅ `DatabaseServiceProvider` já carrega recursivamente

### 3. Adicionar Índices (Opcional)

Ver `ANALISE_INDICES.md` para:
- Tabelas que precisam de índices
- Como criar migrations de alteração
- Prioridades de implementação

## 📋 Checklist

- [x] Documentação criada
- [x] Estrutura ideal definida
- [x] Análise de índices feita
- [ ] Criar pastas (manual ou script)
- [ ] Aplicar gradualmente (novas migrations)
- [ ] Adicionar índices faltantes (quando necessário)

## 🎯 Benefícios

1. **Organização Clara:** Central vs Tenant separados
2. **DDD-Friendly:** Pastas por domínio
3. **Manutenibilidade:** Fácil localizar migrations
4. **Performance:** Índices identificados e documentados
5. **Escalabilidade:** Estrutura preparada para crescimento

## 📚 Referências

- `ESTRUTURA_DDD.md` - Guia completo
- `REORGANIZAR_ESTRUTURA.md` - Mapeamento de migrations
- `ANALISE_INDICES.md` - Análise de performance

