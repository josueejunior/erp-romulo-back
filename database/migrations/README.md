# 📘 Estrutura de Migrations - Organização por Módulos

## 📁 Organização

As migrations estão organizadas por **módulos funcionais**, facilitando a localização e manutenção:

```
database/migrations/
├── Modules/                 # Módulos funcionais do sistema
│   ├── Auth/                # Autenticação e usuários
│   ├── Empresa/             # Empresas e relacionamentos
│   ├── Processo/            # Processos licitatórios
│   ├── Orcamento/           # Orçamentos e itens
│   ├── Contrato/            # Contratos
│   ├── Fornecedor/          # Fornecedores e transportadoras
│   ├── Orgao/               # Órgãos e setores
│   ├── Documento/           # Documentos de habilitação
│   ├── Empenho/             # Empenhos
│   ├── NotaFiscal/          # Notas fiscais
│   ├── AutorizacaoFornecimento/  # Autorizações de fornecimento
│   ├── Custo/               # Custos indiretos
│   ├── Auditoria/           # Logs de auditoria
│   └── Assinatura/          # Planos e assinaturas
│
├── System/                  # Sistema base
│   ├── Cache/               # Cache e locks
│   ├── Jobs/                # Filas de jobs
│   ├── Tokens/              # Tokens de acesso
│   └── Permission/          # Permissões e roles (Spatie)
│
└── Tenancy/                 # Multi-tenancy
    ├── tenants              # Tabela de tenants
    └── domains              # Domínios dos tenants
```

## 🏗️ Organização por Módulos

### Módulos Funcionais (`Modules/`)

**Organização por domínio de negócio**, facilitando localização e manutenção:

- **Localização do código**: `app/Models/`, `app/Services/`, `app/Http/Controllers/`
- **Migrations**: `database/migrations/Modules/{Modulo}/`

**Módulos organizados:**
- `Auth/` - Autenticação e usuários (4 migrations)
- `Empresa/` - Empresas e relacionamentos (2 migrations)
- `Processo/` - Processos licitatórios (4 migrations)
- `Orcamento/` - Orçamentos e itens (3 migrations)
- `Contrato/` - Contratos
- `Fornecedor/` - Fornecedores e transportadoras (2 migrations)
- `Orgao/` - Órgãos e setores (2 migrations)
- `Documento/` - Documentos de habilitação
- `Empenho/` - Empenhos
- `NotaFiscal/` - Notas fiscais
- `AutorizacaoFornecimento/` - Autorizações de fornecimento
- `Custo/` - Custos indiretos
- `Auditoria/` - Logs de auditoria (2 migrations)
- `Assinatura/` - Planos e assinaturas (2 migrations)

### Sistema Base (`System/`)

**Componentes do sistema base**:

- `Cache/` - Cache e locks (2 migrations)
- `Jobs/` - Filas de jobs (3 migrations)
- `Tokens/` - Tokens de acesso (1 migration)
- `Permission/` - Permissões e roles Spatie (5 migrations)

### Tenancy (`Tenancy/`)

**Multi-tenancy**:

- `tenants` - Tabela de tenants
- `domains` - Domínios dos tenants

## 📋 Convenções

### Nomenclatura de Migrations

```
{timestamp}_create_{nome_tabela}_table.php
```

Exemplo:
- `2025_12_13_163310_create_processos_table.php`
- `2025_12_13_163320_create_empresa_user_table.php`

### Estrutura da Migration

```php
<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'nome_tabela';

    public function up(): void
    {
        Schema::create('nome_tabela', function (Blueprint $table) {
            // Usar métodos customizados do Blueprint
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nome_tabela');
    }
};
```

## 🔄 Carregamento Automático

O `DatabaseServiceProvider` carrega todas as migrations recursivamente e ordena por timestamp:

```php
// app/Providers/DatabaseServiceProvider.php
$paths = collect(File::allFiles(database_path('migrations')))
    ->filter(static fn (SplFileInfo $info) => $info->getExtension() === 'php')
    ->sortBy(static fn(SplFileInfo $info) => $info->getFilename())
    ->map(static fn(SplFileInfo $info) => $info->getPath())
    ->unique()
    ->all();
```

## 📝 Boas Práticas

1. **Uma migration = Uma tabela**: Cada migration cria apenas uma tabela
2. **Usar Blueprint customizado**: Sempre use `App\Database\Schema\Blueprint`
3. **Definir `$table`**: Sempre defina a propriedade `$table`
4. **Métodos customizados**: Use `foreignEmpresa()`, `datetimes()`, `observacao()`, etc.
5. **Constantes**: Use `Blueprint::VARCHAR_DEFAULT`, `Blueprint::VARCHAR_SMALL`, etc.
6. **Organização por módulo**: Coloque migrations no módulo correspondente

## 🚀 Criar Nova Migration

### Módulo Funcional

```bash
php artisan make:migration create_nome_tabela \
  --path=database/migrations/Modules/{Modulo}
```

Exemplo:
```bash
php artisan make:migration create_processos_table \
  --path=database/migrations/Modules/Processo
```

### Sistema Base

```bash
php artisan make:migration create_nome_tabela \
  --path=database/migrations/System/{Subsistema}
```

Exemplo:
```bash
php artisan make:migration create_cache_table \
  --path=database/migrations/System/Cache
```

## 📊 Mapeamento: Código ↔ Migrations

| Código | Migrations |
|--------|------------|
| `app/Models/Processo.php` | `database/migrations/Modules/Processo/` |
| `app/Models/User.php` | `database/migrations/Modules/Auth/` |
| `app/Models/Empresa.php` | `database/migrations/Modules/Empresa/` |
| `app/Models/Orcamento.php` | `database/migrations/Modules/Orcamento/` |
| `app/Models/Contrato.php` | `database/migrations/Modules/Contrato/` |
| `app/Models/Plano.php` | `database/migrations/Modules/Assinatura/` |

## 🔍 Estrutura Completa

```
Modules/
├── Auth/
│   ├── create_users_table.php
│   ├── create_admin_users_table.php
│   ├── create_password_reset_tokens_table.php
│   └── create_sessions_table.php
├── Empresa/
│   ├── create_empresas_table.php
│   └── create_empresa_user_table.php
├── Processo/
│   ├── create_processos_table.php
│   ├── create_processo_itens_table.php
│   ├── create_processo_documentos_table.php
│   └── create_processo_item_vinculos_table.php
├── Orcamento/
│   ├── create_orcamentos_table.php
│   ├── create_orcamento_itens_table.php
│   └── create_formacao_precos_table.php
├── Contrato/
│   └── create_contratos_table.php
├── Fornecedor/
│   ├── create_fornecedores_table.php
│   └── create_transportadoras_table.php
├── Orgao/
│   ├── create_orgaos_table.php
│   └── create_setors_table.php
├── Documento/
│   └── create_documentos_habilitacao_table.php
├── Empenho/
│   └── create_empenhos_table.php
├── NotaFiscal/
│   └── create_notas_fiscais_table.php
├── AutorizacaoFornecimento/
│   └── create_autorizacoes_fornecimento_table.php
├── Custo/
│   └── create_custos_indiretos_table.php
├── Auditoria/
│   ├── create_audit_logs_table.php
│   └── create_auditoria_logs_table.php
└── Assinatura/
    ├── create_planos_table.php
    └── create_assinaturas_table.php

System/
├── Cache/
│   ├── create_cache_table.php
│   └── create_cache_locks_table.php
├── Jobs/
│   ├── create_jobs_table.php
│   ├── create_job_batches_table.php
│   └── create_failed_jobs_table.php
├── Tokens/
│   └── create_personal_access_tokens_table.php
└── Permission/
    ├── create_permissions_table.php
    ├── create_roles_table.php
    ├── create_model_has_permissions_table.php
    ├── create_model_has_roles_table.php
    └── create_role_has_permissions_table.php

Tenancy/
├── create_tenants_table.php
└── create_domains_table.php
```

