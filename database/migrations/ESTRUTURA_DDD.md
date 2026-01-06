# 🏗️ Estrutura de Migrations - DDD + Multi-Tenancy

## 🧱 Princípio Base

**Migration é contrato de dados, não detalhe técnico**

Se o contrato é confuso → domínio fica confuso → bugs aparecem → multi-tenant vira caos

## 📁 Estrutura Ideal (DDD-Friendly)

```
database/migrations/
├── central/                    # 🏛️ BANCO CENTRAL (shared)
│   ├── tenancy/               # Multi-tenancy
│   │   ├── 2019_09_15_000010_create_tenants_table.php
│   │   ├── 2019_09_15_000020_create_domains_table.php
│   │   └── 2026_01_06_162213_create_tenant_empresas_table.php
│   │
│   ├── usuarios/              # Usuários globais
│   │   ├── 2025_01_22_000001_create_admin_users_table.php
│   │   └── ...
│   │
│   ├── planos/                # Planos (se global)
│   │   ├── 2025_12_19_000001_create_planos_table.php
│   │   └── ...
│   │
│   ├── cupons/                # Cupons (se global)
│   │   └── 2025_12_31_000001_create_cupons_table.php
│   │
│   └── system/                # Sistema base
│       ├── cache/
│       ├── jobs/
│       ├── tokens/
│       └── permissions/
│
└── tenant/                     # 🏢 BANCO TENANT (operacional)
    ├── empresas/
    │   ├── 2025_12_13_163303_create_empresas_table.php
    │   └── 2025_12_13_163320_create_empresa_user_table.php
    │
    ├── assinaturas/
    │   ├── 2025_12_19_000002_create_assinaturas_table.php
    │   └── 2026_01_06_140000_add_user_id_to_assinaturas_table.php
    │
    ├── processos/
    │   ├── 2025_12_13_163310_create_processos_table.php
    │   ├── 2025_12_13_163311_create_processo_itens_table.php
    │   ├── 2025_12_13_163312_create_processo_documentos_table.php
    │   └── 2025_12_16_100011_create_processo_item_vinculos_table.php
    │
    ├── orcamentos/
    │   ├── 2025_12_13_163312_create_orcamentos_table.php
    │   ├── 2025_12_13_163313_create_orcamento_itens_table.php
    │   └── 2025_12_13_163314_create_formacao_precos_table.php
    │
    ├── contratos/
    │   └── 2025_12_13_163314_create_contratos_table.php
    │
    ├── fornecedores/
    │   ├── 2025_12_13_163307_create_fornecedores_table.php
    │   └── 2025_12_13_163309_create_transportadoras_table.php
    │
    ├── orgaos/
    │   ├── 2025_12_13_163305_create_orgaos_table.php
    │   ├── 2025_12_13_163306_create_setors_table.php
    │   └── 2025_12_31_130000_create_orgao_responsaveis_table.php
    │
    ├── documentos/
    │   ├── 2025_12_13_163309_create_documentos_habilitacao_table.php
    │   ├── 2025_12_31_150000_create_documento_habilitacao_versoes_table.php
    │   └── 2025_12_31_150100_create_documento_habilitacao_logs_table.php
    │
    ├── empenhos/
    │   └── 2025_12_13_163316_create_empenhos_table.php
    │
    ├── notas_fiscais/
    │   └── 2025_12_13_163317_create_notas_fiscais_table.php
    │
    ├── autorizacoes_fornecimento/
    │   └── 2025_12_13_163315_create_autorizacoes_fornecimento_table.php
    │
    ├── custos/
    │   └── 2025_12_13_163317_create_custos_indiretos_table.php
    │
    └── auditoria/
        └── 2025_01_21_000001_create_audit_logs_table.php
```

## 🧠 Regra de Ouro Multi-Tenancy

### 🏛️ Central DB (Shared)

**Contém:**
- ✅ `tenants` - Lista de tenants
- ✅ `domains` - Domínios dos tenants
- ✅ `tenant_empresas` - Mapeamento empresa → tenant
- ✅ `admin_users` - Usuários administrativos globais
- ✅ `planos` - Planos disponíveis (se global)
- ✅ `cupons` - Cupons de desconto (se global)
- ✅ `permissions`, `roles` - Permissões globais
- ✅ `cache`, `jobs`, `sessions` - Sistema base

**📍 Nunca dados operacionais**

### 🏢 Tenant DB

**Contém:**
- ✅ `empresas` - Empresas do tenant
- ✅ `assinaturas` - Assinaturas dos usuários
- ✅ `processos` - Processos licitatórios
- ✅ `contratos` - Contratos
- ✅ `orcamentos` - Orçamentos
- ✅ `fornecedores` - Fornecedores
- ✅ `empenhos` - Empenhos
- ✅ `notas_fiscais` - Notas fiscais
- ✅ Tudo que "pertence" ao tenant

**📍 Nunca dados globais**

## 🧩 Princípios de Organização

### 1️⃣ Uma Migration = Uma Responsabilidade

❌ **Errado:**
```php
create_empresas_e_assinaturas_e_contratos_tables
```

✅ **Certo:**
```php
create_empresas_table
create_assinaturas_table
create_contratos_table
```

### 2️⃣ Ordem Explícita (Prefixos Lógicos)

```
2025_12_13_163310_create_processos_table
2025_12_13_163311_create_processo_itens_table
2025_12_13_163312_create_processo_documentos_table
```

Facilita:
- ✅ Rollback
- ✅ Debug
- ✅ Deploy incremental

### 3️⃣ Nomes Semânticos

❌ **Evite:**
```php
valor
status
data
```

✅ **Prefira:**
```php
valor_total
valor_pago
status_assinatura
data_inicio
data_fim
```

## 🔗 Relacionamentos e Chaves

### Foreign Keys (com cuidado)

**No Tenant DB:**
✅ Usar foreign keys normalmente
```php
$table->foreignId('empresa_id')->constrained('empresas');
$table->foreignId('processo_id')->constrained('processos');
```

**No Central DB:**
⚠️ Só se fizer sentido global
```php
// ✅ OK - dentro do mesmo banco
$table->foreignId('plano_id')->constrained('planos');

// ❌ EVITAR - tenant → central
// Prefira validação em código
```

## ⚡ Performance nas Migrations

### Índices Obrigatórios

**Sempre indexar:**
- ✅ `tenant_id` (se aplicável)
- ✅ `empresa_id`
- ✅ `user_id`
- ✅ `status`
- ✅ `data_inicio`, `data_fim`
- ✅ Campos usados em `WHERE` frequentes

```php
$table->index(['user_id', 'status']);
$table->index('data_inicio');
$table->index('data_fim');
```

## 🧪 Padrões que Salvam no Futuro

### 1️⃣ Nunca Alterar Migration Antiga

❌ **Nunca:**
- Editar migration já rodada
- Mudar coluna em migration antiga

✅ **Sempre:**
```bash
php artisan make:migration add_x_to_y_table
```

### 2️⃣ Migration Exemplo (Tenant)

```php
Schema::create('assinaturas', function (Blueprint $table) {
    $table->id();
    
    // Foreign keys
    $table->unsignedBigInteger('user_id')->index();
    $table->unsignedBigInteger('plano_id')->nullable()->index();
    
    // Status e datas (sempre indexar)
    $table->string('status')->index();
    $table->date('data_inicio')->index();
    $table->date('data_fim')->nullable()->index();
    
    // Valores
    $table->decimal('valor_pago', 10, 2)->default(0);
    
    // Timestamps
    $table->datetimes();
    
    // Índices compostos para queries frequentes
    $table->index(['user_id', 'status']);
    $table->index(['data_inicio', 'data_fim']);
});
```

## 🎯 Checklist Final

### Organização
- [ ] Migrations separadas por `central/` / `tenant/`
- [ ] Pastas por domínio (empresas/, processos/, etc)
- [ ] Nomes claros e semânticos
- [ ] Ordem explícita com prefixos

### Segurança
- [ ] FKs onde faz sentido
- [ ] Validação em código para cross-DB
- [ ] Constraints de integridade

### Performance
- [ ] Índices em tudo que filtra
- [ ] Índices compostos para queries frequentes
- [ ] Nada de loop de tenant em migration

## 🚀 Criar Nova Migration

### Central DB
```bash
php artisan make:migration create_nome_tabela \
  --path=database/migrations/central/{dominio}
```

### Tenant DB
```bash
php artisan make:migration create_nome_tabela \
  --path=database/migrations/tenant/{dominio}
```

## 📊 Mapeamento: Código ↔ Migrations

| Código | Migration |
|--------|-----------|
| `app/Models/Tenant.php` | `central/tenancy/` |
| `app/Models/Empresa.php` | `tenant/empresas/` |
| `app/Modules/Processo/Models/Processo.php` | `tenant/processos/` |
| `app/Modules/Assinatura/Models/Assinatura.php` | `tenant/assinaturas/` |

## 🧠 Veredito Final

👉 **Migration boa:**
- Ninguém mexe
- Ninguém quebra
- Todo mundo entende

👉 **Migration ruim:**
- Vira dívida técnica silenciosa
- Bugs aparecem tarde
- Multi-tenant vira caos

