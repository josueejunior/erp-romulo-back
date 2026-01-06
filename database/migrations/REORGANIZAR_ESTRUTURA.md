# 🔄 Guia de Reorganização de Migrations

## 📋 Estrutura Atual vs Nova

### Estrutura Atual (Misturada)
```
migrations/
├── [raiz com muitas migrations]
├── Modules/ (algumas migrations)
├── System/ (algumas migrations)
├── Tenancy/ (tenants, domains, tenant_empresas)
└── tenant/ (migrations do tenant, mas desorganizadas)
```

### Estrutura Nova (DDD-Friendly)
```
migrations/
├── central/           # 🏛️ BANCO CENTRAL
│   ├── tenancy/
│   ├── usuarios/
│   ├── planos/
│   ├── cupons/
│   └── system/
└── tenant/            # 🏢 BANCO TENANT
    ├── empresas/
    ├── assinaturas/
    ├── processos/
    ├── orcamentos/
    └── [outros domínios]
```

## 🗂️ Mapeamento de Migrations

### CENTRAL DB

| Migration Atual | Nova Localização |
|----------------|-----------------|
| `Tenancy/2019_09_15_000010_create_tenants_table.php` | `central/tenancy/` |
| `Tenancy/2019_09_15_000020_create_domains_table.php` | `central/tenancy/` |
| `Tenancy/2026_01_06_162213_create_tenant_empresas_table.php` | `central/tenancy/` |
| `2025_01_22_000001_create_admin_users_table.php` | `central/usuarios/` |
| `2025_12_19_000001_create_planos_table.php` | `central/planos/` (se global) |
| `2025_12_31_000001_create_cupons_table.php` | `central/cupons/` (se global) |
| `System/Cache/*` | `central/system/cache/` |
| `System/Jobs/*` | `central/system/jobs/` |
| `System/Tokens/*` | `central/system/tokens/` |
| `System/Permission/*` | `central/system/permissions/` |

### TENANT DB

| Migration Atual | Nova Localização |
|----------------|-----------------|
| `tenant/2025_12_13_163303_create_empresas_table.php` | `tenant/empresas/` |
| `tenant/2025_12_13_163320_create_empresa_user_table.php` | `tenant/empresas/` |
| `tenant/2025_12_19_000002_create_assinaturas_table.php` | `tenant/assinaturas/` |
| `tenant/2025_12_13_163310_create_processos_table.php` | `tenant/processos/` |
| `tenant/2025_12_13_163311_create_processo_itens_table.php` | `tenant/processos/` |
| `tenant/2025_12_13_163312_create_processo_documentos_table.php` | `tenant/processos/` |
| `tenant/2025_12_13_163312_create_orcamentos_table.php` | `tenant/orcamentos/` |
| `tenant/2025_12_13_163314_create_contratos_table.php` | `tenant/contratos/` |
| `tenant/2025_12_13_163307_create_fornecedores_table.php` | `tenant/fornecedores/` |
| `tenant/2025_12_13_163305_create_orgaos_table.php` | `tenant/orgaos/` |
| `tenant/2025_12_13_163309_create_documentos_habilitacao_table.php` | `tenant/documentos/` |
| `tenant/2025_12_13_163316_create_empenhos_table.php` | `tenant/empenhos/` |
| `tenant/2025_12_13_163317_create_notas_fiscais_table.php` | `tenant/notas_fiscais/` |
| `tenant/2025_12_13_163315_create_autorizacoes_fornecimento_table.php` | `tenant/autorizacoes_fornecimento/` |
| `tenant/2025_12_13_163317_create_custos_indiretos_table.php` | `tenant/custos/` |
| `tenant/2025_01_21_000001_create_audit_logs_table.php` | `tenant/auditoria/` |

## ⚠️ IMPORTANTE: Não Mover Migrations Já Executadas

**Regra de Ouro:**
- ✅ Migrations já executadas em produção **NÃO devem ser movidas**
- ✅ Apenas novas migrations devem seguir a nova estrutura
- ✅ Migrations antigas podem ficar onde estão (compatibilidade)

## 🚀 Como Aplicar (Gradual)

### Opção 1: Apenas Novas Migrations (Recomendado)

1. Criar estrutura de pastas:
```bash
mkdir -p database/migrations/central/{tenancy,usuarios,planos,cupons,system/{cache,jobs,tokens,permissions}}
mkdir -p database/migrations/tenant/{empresas,assinaturas,processos,orcamentos,contratos,fornecedores,orgaos,documentos,empenhos,notas_fiscais,autorizacoes_fornecimento,custos,auditoria}
```

2. Novas migrations seguem a nova estrutura
3. Migrations antigas ficam onde estão

### Opção 2: Reorganização Completa (Apenas em Dev)

⚠️ **Só fazer em ambiente de desenvolvimento!**

1. Fazer backup completo do banco
2. Executar script de reorganização
3. Testar migrations
4. Aplicar em produção apenas após validação completa

## 📝 Checklist de Índices

Verificar se as migrations têm índices em:

- [ ] `empresa_id` (se aplicável)
- [ ] `user_id` (se aplicável)
- [ ] `tenant_id` (se aplicável)
- [ ] `status` (se aplicável)
- [ ] `data_inicio`, `data_fim` (se aplicável)
- [ ] Campos usados em `WHERE` frequentes

## 🎯 Próximos Passos

1. ✅ Criar estrutura de pastas
2. ✅ Documentar estrutura ideal
3. ⏳ Aplicar gradualmente (novas migrations)
4. ⏳ Adicionar índices faltantes (quando necessário)

