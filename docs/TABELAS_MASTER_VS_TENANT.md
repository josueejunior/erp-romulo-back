# Tabelas: Master (central) vs Tenant

Em modo **multi-database**, o banco **master** (central) e cada banco **tenant** (tenant_1, tenant_2, …) têm conjuntos diferentes de tabelas.

---

## ✅ Só no MASTER (central) – não devem existir no tenant

| Tabela | Descrição |
|--------|-----------|
| `_migrations` | Controle de migrations do **próprio** banco (cada DB tem a sua) |
| `tenants` | Cadastro de todos os tenants (empresas) |
| `domains` | Domínios (Stancl Tenancy) |
| `tenant_empresas` | Mapeamento tenant_id ↔ empresa_id |
| `users_lookup` | Lookup central: email → tenant_id, user_id (login) |
| `admin_users` | Usuários admin do painel central |
| `planos` | Planos de assinatura (catálogo global) |
| `cupons` | Cupons de desconto (central) |
| `cupons_uso` | Uso de cupons |
| `afiliados` | Cadastro de afiliados |
| `afiliado_referencias` | Referências de afiliados |
| `afiliado_indicacoes` | Indicações |
| `afiliado_comissoes_recorrentes` | Comissões recorrentes |
| `afiliado_pagamentos_comissoes` | Pagamentos de comissão |
| `cache` | Cache (driver database) |
| `cache_locks` | Lock do cache |
| `jobs` | Filas Laravel |
| `job_batches` | Batches de jobs |
| `failed_jobs` | Jobs que falharam |
| `sessions` | Sessões web (se usar) |
| `password_reset_tokens` | Reset de senha (central) |
| `personal_access_tokens` | Tokens Sanctum (central) |
| `onboarding_progress` | Progresso de onboarding (central, se for global) |
| `permissions` | *(central: só se admin usar Spatie no central)* |
| `roles` | *(idem)* |
| `model_has_permissions` | *(idem)* |
| `model_has_roles` | *(idem)* |
| `role_has_permissions` | *(idem)* |

---

## ✅ Só no TENANT – não devem existir no master

Estas tabelas são **por tenant** (cada banco tenant_1, tenant_2, … tem a sua cópia). No master **não** devem existir (ou foram criadas por engano quando tudo rodava no mesmo DB).

| Tabela | Descrição |
|--------|-----------|
| `empresas` | Empresas do tenant |
| `empresa_user` | Vínculo user ↔ empresa |
| `users` | Usuários do tenant |
| `user_notification_preferences` | Preferências de notificação |
| `processos` | Processos licitatórios |
| `processo_itens` | Itens do processo |
| `processo_documentos` | Documentos do processo |
| `processo_item_vinculos` | Vínculos de itens |
| `orcamentos` | Orçamentos |
| `orcamento_itens` | Itens de orçamento |
| `formacao_precos` | Formação de preços |
| `documentos_habilitacao` | Documentos de habilitação |
| `documento_habilitacao_versoes` | Versões |
| `documento_habilitacao_logs` | Logs |
| `orgaos` | Órgãos |
| `setors` | Setores |
| `orgao_responsaveis` | Responsáveis por órgão |
| `fornecedores` | Fornecedores |
| `transportadoras` | Transportadoras |
| `empenhos` | Empenhos |
| `notas_fiscais` | Notas fiscais |
| `contratos` | Contratos |
| `custo_indiretos` | Custos indiretos |
| `autorizacoes_fornecimento` | Autorizações de fornecimento |
| `notificacoes` | Notificações do tenant |
| `produtos` | Produtos |
| `audit_logs` | Auditoria do tenant |
| `assinaturas` | Assinaturas **do tenant** (no seu projeto também existe no tenant) |
| `payment_logs` | Logs de pagamento **do tenant** |
| `permissions` | Roles/permissions **do tenant** (Spatie por tenant) |
| `roles` | |
| `model_has_permissions` | |
| `model_has_roles` | |
| `role_has_permissions` | |
| `onboarding_progress` | *(se for por tenant)* |

---

## Por que o master está com tabelas de tenant?

Isso costuma acontecer quando:

1. **Antes** o sistema era single-database: todas as migrations (central + tenant) rodavam no mesmo banco.
2. Ou **por engano** foi rodado `php artisan migrate` (sem separar central/tenant) e as migrations de tenant foram aplicadas no banco default (master).

Com **multi-database**:

- **Master**: só migrations em `database/migrations/central/` (e o default do Laravel para esse banco).
- **Cada tenant**: só migrations em `database/migrations/tenant/`.

---

## Como “limpar” o master (só depois de garantir dados nos tenants)

**Atenção:** só faça isso depois de:

- Ter **todos** os tenants com banco criado e migrations rodadas (`tenants:migrate`).
- Ter **migrado/copiado** os dados que estavam no master para o banco de cada tenant (se ainda havia dados nessas tabelas no central).

Depois disso, no **banco master** você pode dropar as tabelas que são **só de tenant** (a lista “Só no TENANT” acima). Exemplo em SQL (PostgreSQL), **por sua conta e risco**:

```sql
-- NÃO rode em produção sem backup e sem ter conferido os tenants.
-- Exemplo: dropar tabelas que devem existir só no tenant (ajuste conforme sua lista).
/*
DROP TABLE IF EXISTS tenant_empresas CASCADE;
-- não dropar tenant_empresas no central! ela é central.
-- dropar apenas as que são 100% tenant:
DROP TABLE IF EXISTS empresas CASCADE;
DROP TABLE IF EXISTS empresa_user CASCADE;
DROP TABLE IF EXISTS users CASCADE;
...
*/
```

Recomendação: fazer um **backup** do master, depois dropar **uma a uma** as tabelas que são só de tenant, conferindo que nenhum processo usa essas tabelas no banco central.

---

## Resumo rápido

- **Master:** tenants, domains, users_lookup, admin_users, planos, cupons, afiliados, filas (jobs), cache, sessions, tokens, tenant_empresas, onboarding_progress (se central), _migrations.
- **Tenant:** empresas, users, processos, orcamentos, documentos, orgaos, fornecedores, empenhos, notas_fiscais, contratos, assinaturas, payment_logs, permissions/roles (Spatie), notificacoes, audit_logs, etc.

As tabelas listadas em **“Só no TENANT”** não devem estar no master quando o modo multi-database estiver em uso.
