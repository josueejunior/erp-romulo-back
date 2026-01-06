# 🎯 O Que Falta - Regra DDD

## 📋 Princípio DDD: Uma Migration = Um Domínio

**Regra de Ouro:**
- ✅ Cada domínio tem sua pasta
- ✅ Migrations de criação vão para a pasta do domínio
- ✅ Migrations de alteração (`add_*`, `alter_*`) vão para a pasta do domínio afetado
- ✅ Central DB separado de Tenant DB

## ⚠️ Migrations na Raiz `tenant/` (Violam DDD)

### 🔴 CRÍTICO - Migrations de Criação na Raiz

Estas migrations **devem** estar organizadas por domínio:

#### Processos
- ❌ `2025_12_13_163310_create_processos_table.php` → `tenant/processos/` ✅ (já feito)
- ❌ `2025_12_13_163311_create_processo_itens_table.php` → `tenant/processos/` ✅ (já feito)
- ❌ `2025_12_13_163312_create_processo_documentos_table.php` → `tenant/processos/` ✅ (já feito)
- ❌ `2025_12_16_100011_create_processo_item_vinculos_table.php` → `tenant/processos/` ✅ (já feito)

#### Orgaos
- ❌ `2025_12_13_163305_create_orgaos_table.php` → `tenant/orgaos/` ✅ (já feito)
- ❌ `2025_12_13_163306_create_setors_table.php` → `tenant/orgaos/` ✅ (já feito)
- ❌ `2025_12_31_130000_create_orgao_responsaveis_table.php` → `tenant/orgaos/` ⚠️ **FALTA**

#### Fornecedores
- ❌ `2025_12_13_163307_create_fornecedores_table.php` → `tenant/fornecedores/` ✅ (já feito)
- ❌ `2025_12_13_163309_create_transportadoras_table.php` → `tenant/fornecedores/` ✅ (já feito)

#### Documentos
- ❌ `2025_12_13_163309_create_documentos_habilitacao_table.php` → `tenant/documentos/` ✅ (já feito)
- ❌ `2025_12_31_140000_add_ativo_to_documentos_habilitacao_table.php` → `tenant/documentos/` ⚠️ **FALTA**

#### Orcamentos
- ❌ `2025_12_13_163312_create_orcamentos_table.php` → `tenant/orcamentos/` ✅ (já feito)
- ❌ `2025_12_13_163313_create_orcamento_itens_table.php` → `tenant/orcamentos/` ✅ (já feito)
- ❌ `2025_12_31_120000_add_transportadora_id_to_orcamentos_table.php` → `tenant/orcamentos/` ⚠️ **FALTA**
- ❌ `2026_01_04_130000_add_processo_item_id_to_orcamentos_table.php` → `tenant/orcamentos/` ⚠️ **FALTA**
- ❌ `2026_01_04_130100_add_missing_columns_to_orcamentos_table.php` → `tenant/orcamentos/` ⚠️ **FALTA**

#### Processos (Alterações)
- ❌ `2025_12_31_130001_add_orgao_responsavel_id_to_processos_table.php` → `tenant/processos/` ⚠️ **FALTA**
- ❌ `2025_12_31_150000_add_fornecedor_transportadora_to_processo_itens_table.php` → `tenant/processos/` ⚠️ **FALTA**
- ❌ `2026_01_05_192700_alter_processos_objeto_resumido_to_text.php` → `tenant/processos/` ⚠️ **FALTA**

#### Auditoria
- ❌ `2025_01_21_000001_create_audit_logs_table.php` → `tenant/auditoria/` ✅ (já feito)

### 🟡 MÉDIO - Migrations em Subpastas Antigas

Estas estão em pastas antigas e precisam ser movidas:

#### Documentos
- ❌ `tenant/Documento/2025_12_31_150000_create_documento_habilitacao_versoes_table.php` → `tenant/documentos/` ⚠️ **FALTA**
- ❌ `tenant/Documento/2025_12_31_150100_create_documento_habilitacao_logs_table.php` → `tenant/documentos/` ⚠️ **FALTA**

#### Orcamentos
- ❌ `tenant/Orcamento/2025_12_31_180000_create_notificacoes_table.php` → `tenant/orcamentos/` ou `tenant/notificacoes/` ⚠️ **FALTA**

#### Processos
- ❌ `tenant/Processo/2025_12_31_160000_update_processo_documentos_add_status_and_custom.php` → `tenant/processos/` ⚠️ **FALTA**

## 🏛️ Central DB - O Que Falta

### Usuários
- ❌ `2025_01_22_000001_create_admin_users_table.php` → `central/usuarios/` ⚠️ **FALTA**
- ❌ `0001_01_01_000001_create_password_reset_tokens_table.php` → `central/usuarios/` ⚠️ **FALTA**
- ❌ `0001_01_01_000002_create_sessions_table.php` → `central/usuarios/` ⚠️ **FALTA**
- ❌ `2025_12_30_000001_add_foto_perfil_to_users_table.php` → `central/usuarios/` ⚠️ **FALTA**

### Planos
- ❌ `2025_12_19_000001_create_planos_table.php` → `central/planos/` ⚠️ **FALTA**

### Cupons
- ❌ `2025_12_31_000001_create_cupons_table.php` → `central/cupons/` ⚠️ **FALTA**

### System
- ❌ `System/Cache/*` → `central/system/cache/` ⚠️ **FALTA**
- ❌ `System/Jobs/*` → `central/system/jobs/` ⚠️ **FALTA**
- ❌ `System/Tokens/*` → `central/system/tokens/` ⚠️ **FALTA**
- ❌ `System/Permission/*` → `central/system/permissions/` ⚠️ **FALTA**

### Tenancy (Duplicação)
- ❌ `2019_09_15_000020_create_domains_table.php` (raiz) → Já existe em `central/tenancy/` ⚠️ **REMOVER DUPLICATA**

## 📊 Resumo por Prioridade DDD

### 🔴 ALTA PRIORIDADE (Violam DDD claramente)

**Tenant DB:**
- 8 migrations de alteração na raiz que deveriam estar em seus domínios
- 4 migrations em subpastas antigas

**Central DB:**
- 4 migrations de usuários na raiz
- 1 migration de planos na raiz
- 1 migration de cupons na raiz
- Migrations de System em pasta antiga

**Total: ~19 migrations críticas**

### 🟡 MÉDIA PRIORIDADE

- Migrations já executadas podem ficar onde estão (compatibilidade)
- Duplicatas podem ser removidas após validação

## ✅ Checklist DDD

- [ ] Todas as migrations de criação organizadas por domínio
- [ ] Todas as migrations de alteração no domínio correto
- [ ] Central DB completamente separado
- [ ] Tenant DB completamente separado
- [ ] Sem duplicatas
- [ ] Sem migrations na raiz (exceto compatibilidade)

## 🎯 Ação Recomendada

1. **Organizar migrations de alteração** para seus domínios
2. **Mover migrations Central** para `central/`
3. **Mover migrations de subpastas antigas** para estrutura DDD
4. **Remover duplicatas** após validação

