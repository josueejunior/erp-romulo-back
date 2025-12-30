# 🗄️ Arquitetura de Migrations

## Visão Geral

Esta arquitetura implementa um sistema padronizado de migrations com:

- **Classe base customizada** (`App\Database\Migrations\Migration`)
- **Blueprint customizado** (`App\Database\Schema\Blueprint`) com métodos auxiliares
- **Carregamento automático** de migrations organizadas por módulos
- **Timestamps em português** (criado_em, atualizado_em, excluido_em)
- **Tabela de controle** `_migrations` (não `migrations`)

## Componentes

### 1. Classe Base `Migration`

**Localização:** `app/Database/Migrations/Migration.php`

Todas as migrations devem estender esta classe:

```php
use App\Database\Migrations\Migration;

return new class extends Migration
{
    public string $table = 'nome_tabela';
    
    public function up(): void { }
    public function down(): void { }
};
```

### 2. Blueprint Customizado

**Localização:** `app/Database/Schema/Blueprint.php`

#### Constantes de Tamanho

```php
Blueprint::VARCHAR_TINY = 50;
Blueprint::VARCHAR_SMALL = 100;
Blueprint::VARCHAR_DEFAULT = 250;
Blueprint::VARCHAR_MEDIUM = 1000;
Blueprint::VARCHAR_LARGE = 2500;
Blueprint::VARCHAR_EXTRA_LARGE = 5000;
```

#### Timestamps

```php
Blueprint::CREATED_AT = 'criado_em';
Blueprint::UPDATED_AT = 'atualizado_em';
Blueprint::DELETED_AT = 'excluido_em';
```

#### Métodos de Foreign Keys

```php
$table->foreignEmpresa();              // empresa_id -> empresas
$table->foreignTenant();               // tenant_id -> tenants
$table->foreignUsuario();              // usuario_id -> users
$table->foreignPessoa();               // pessoa_id -> pessoas
$table->foreignIdCustom('coluna', 'tabela', $nullable, $onDelete);
```

#### Métodos de Campos Comuns

```php
$table->endereco();                    // cep, logradouro, numero, bairro, complemento, cidade, estado
$table->coordenadas();                 // latitude, longitude
$table->email('coluna', $nullable);    // String VARCHAR_DEFAULT
$table->telefone('coluna', $nullable); // String 15 caracteres
$table->descricao('coluna', $nullable); // String VARCHAR_DEFAULT
$table->observacao('coluna', $nullable); // Text
$table->ativo();                       // Boolean default true
$table->status($values, $default);     // Enum com valores
$table->datetimes();                   // criado_em, atualizado_em
$table->datetimesWithSoftDeletes();   // criado_em, atualizado_em, excluido_em
```

### 3. DatabaseServiceProvider

**Localização:** `app/Providers/DatabaseServiceProvider.php`

Carrega migrations recursivamente de todas as pastas e ordena por timestamp.

### 4. SchemaServiceProvider

**Localização:** `app/Providers/SchemaServiceProvider.php`

Registra o Blueprint customizado para uso automático.

## Estrutura de Pastas

```
database/migrations/
├── Modules/              # Módulos principais
│   ├── Processo/
│   ├── Contrato/
│   ├── Orcamento/
│   └── ...
├── Contexts/            # Contextos específicos
│   ├── Admin/
│   ├── Tenant/
│   └── Shared/
└── Support/             # Funções e triggers
```

## Exemplo Completo

```php
<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processos';

    public function up(): void
    {
        Schema::create('processos', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignEmpresa();
            $table->foreignIdCustom('orgao_id', 'orgaos', true, 'cascade');
            $table->foreignUsuario(true);
            
            // Campos principais
            $table->string('numero_modalidade', Blueprint::VARCHAR_DEFAULT);
            $table->enum('modalidade', ['pregao', 'tomada_preco', 'convite'])->default('pregao');
            $table->descricao('objeto_resumido');
            $table->observacao();
            
            // Contato
            $table->email('email_contato', true);
            $table->telefone('telefone_contato', true);
            
            // Endereço
            $table->endereco();
            
            // Status
            $table->status(['rascunho', 'publicado', 'encerrado'], 'rascunho');
            $table->ativo();
            
            // Timestamps
            $table->datetimes();
            
            // Índices
            $table->index('numero_modalidade');
            $table->index(['empresa_id', 'status']);
            $table->unique(['empresa_id', 'numero_modalidade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processos');
    }
};
```

## Nomenclatura

### Arquivos de Migration

- Formato: `{timestamp}_create_{nome_tabela}.php`
- Exemplo: `2025_01_01_000011_create_processo.php`
- **Não usar** `create_table_`, apenas `create_`

### Tabelas

- Nomes em português, plural
- Exemplos: `processos`, `contratos`, `orcamentos`

### Foreign Keys

- Formato: `{tabela}_id` (sem sufixo adicional)
- Exemplos: `processo_id`, `orgao_id`, `empresa_id`
- Evitar: `processo_id_id` (repetitivo)

## Configuração

### Tabela de Migrations

A tabela de controle é `_migrations` (configurado em `config/database.php`):

```php
'migrations' => [
    'table' => '_migrations',
    'update_date_on_publish' => true,
],
```

### Service Providers

Registrados em `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\SchemaServiceProvider::class,
    App\Providers\DatabaseServiceProvider::class,
];
```

## Uso

### Criar Nova Migration

```bash
php artisan make:migration create_processo --path=database/migrations/Modules/Processo
```

### Executar Migrations

```bash
php artisan migrate
```

O sistema carregará automaticamente todas as migrations de todas as pastas, ordenadas por timestamp.

### Rollback

```bash
php artisan migrate:rollback
```

## Vantagens

1. **Padronização**: Todos usam os mesmos métodos auxiliares
2. **Organização**: Migrations organizadas por módulos
3. **Manutenibilidade**: Código mais limpo e legível
4. **Reutilização**: Métodos auxiliares evitam repetição
5. **Timestamps em Português**: Mais intuitivo para o time brasileiro

## Migração de Migrations Existentes

Para migrar migrations existentes:

1. Trocar `use Illuminate\Database\Migrations\Migration;` por `use App\Database\Migrations\Migration;`
2. Trocar `use Illuminate\Database\Schema\Blueprint;` por `use App\Database\Schema\Blueprint;`
3. Usar métodos auxiliares do Blueprint quando possível
4. Usar constantes de tamanho (`Blueprint::VARCHAR_DEFAULT`)
5. Usar `datetimes()` em vez de `timestamps()` se quiser nomes em português





