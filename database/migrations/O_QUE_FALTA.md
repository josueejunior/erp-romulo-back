# 📋 O Que Ainda Falta Organizar

## ⚠️ Migrations na Raiz do `tenant/` que Precisam Ser Movidas

### 🏢 Orgaos
- ❌ `2025_12_13_163305_create_orgaos_table.php` → `tenant/orgaos/`
- ❌ `2025_12_13_163306_create_setors_table.php` → `tenant/orgaos/`
- ❌ `2025_12_31_130000_create_orgao_responsaveis_table.php` → `tenant/orgaos/`
- ❌ `2025_12_31_130001_add_orgao_responsavel_id_to_processos_table.php` → `tenant/processos/`

### 🏭 Fornecedores
- ❌ `2025_12_13_163307_create_fornecedores_table.php` → `tenant/fornecedores/`
- ❌ `2025_12_13_163309_create_transportadoras_table.php` → `tenant/fornecedores/`

### 📄 Documentos
- ❌ `2025_12_13_163309_create_documentos_habilitacao_table.php` → `tenant/documentos/`
- ❌ `2025_12_31_140000_add_ativo_to_documentos_habilitacao_table.php` → `tenant/documentos/`
- ❌ `tenant/Documento/2025_12_31_150000_create_documento_habilitacao_versoes_table.php` → `tenant/documentos/`
- ❌ `tenant/Documento/2025_12_31_150100_create_documento_habilitacao_logs_table.php` → `tenant/documentos/`

### 💰 Orcamentos
- ❌ `2025_12_13_163312_create_orcamentos_table.php` → `tenant/orcamentos/`
- ❌ `2025_12_13_163313_create_orcamento_itens_table.php` → `tenant/orcamentos/`
- ❌ `2025_12_31_120000_add_transportadora_id_to_orcamentos_table.php` → `tenant/orcamentos/`
- ❌ `2026_01_04_130000_add_processo_item_id_to_orcamentos_table.php` → `tenant/orcamentos/`
- ❌ `2026_01_04_130100_add_missing_columns_to_orcamentos_table.php` → `tenant/orcamentos/`
- ❌ `tenant/Orcamento/2025_12_31_180000_create_notificacoes_table.php` → `tenant/orcamentos/` (ou criar `tenant/notificacoes/`)

### 📦 Notas Fiscais
- ❌ `2025_12_13_163317_create_notas_fiscais_table.php` → `tenant/notas_fiscais/`

### 💵 Custos
- ❌ `2025_12_13_163317_create_custos_indiretos_table.php` → `tenant/custos/`

### 📋 Processos (alterações)
- ❌ `2025_12_31_150000_add_fornecedor_transportadora_to_processo_itens_table.php` → `tenant/processos/`
- ❌ `2026_01_05_192700_alter_processos_objeto_resumido_to_text.php` → `tenant/processos/`
- ❌ `tenant/Processo/2025_12_31_160000_update_processo_documentos_add_status_and_custom.php` → `tenant/processos/`

### 🏢 Empresas
- ❌ Verificar se há migrations de empresas na raiz

### 📝 Assinaturas
- ❌ Verificar se há migrations de assinaturas na raiz

## 🏛️ Central DB - O Que Falta

### Usuários
- ❌ `2025_01_22_000001_create_admin_users_table.php` → `central/usuarios/`

### Planos
- ❌ `2025_12_19_000001_create_planos_table.php` → `central/planos/` (se global)

### Cupons
- ❌ `2025_12_31_000001_create_cupons_table.php` → `central/cupons/` (se global)

### System
- ❌ Migrations de `System/Cache/` → `central/system/cache/`
- ❌ Migrations de `System/Jobs/` → `central/system/jobs/`
- ❌ Migrations de `System/Tokens/` → `central/system/tokens/`
- ❌ Migrations de `System/Permission/` → `central/system/permissions/`

## 📊 Resumo

**Total de migrations pendentes:**
- Tenant: ~20 migrations
- Central: ~10 migrations
- **Total: ~30 migrations**

## ⚠️ Importante

**NÃO mover migrations já executadas em produção!**

A estratégia recomendada é:
1. ✅ Novas migrations seguem a nova estrutura
2. ✅ Migrations antigas podem ficar onde estão (compatibilidade)
3. ✅ O `DatabaseServiceProvider` carrega recursivamente, então ambas funcionam

## 🎯 Prioridade

**Alta:**
- Migrations de criação de tabelas principais
- Migrations que serão usadas em novos ambientes

**Média:**
- Migrations de alteração (add_*, alter_*)
- Migrations em subpastas antigas

**Baixa:**
- Migrations já executadas em produção (deixar onde estão)

