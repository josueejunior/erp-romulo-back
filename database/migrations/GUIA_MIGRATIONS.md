# 📘 Guia de Uso: Arquitetura de Migrations

## Como Usar

### 1. Criar Nova Migration

```bash
# Migration na raiz
php artisan make:migration create_nome_tabela

# Migration em módulo específico
php artisan make:migration create_processo --path=database/migrations/Modules/Processo
```

### 2. Estrutura da Migration

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
            // Seu código aqui usando métodos auxiliares
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nome_tabela');
    }
};
```

### 3. Métodos Disponíveis

#### Foreign Keys

```php
$table->foreignEmpresa();                    // empresa_id -> empresas (cascade)
$table->foreignEmpresa(true);                // nullable
$table->foreignTenant();                     // tenant_id -> tenants
$table->foreignUsuario(true);                // usuario_id -> users (set null)
$table->foreignPessoa(true);                 // pessoa_id -> pessoas
$table->foreignIdCustom('orgao_id', 'orgaos', true, 'cascade');
```

#### Campos Comuns

```php
$table->endereco();                          // cep, logradouro, numero, bairro, complemento, cidade, estado
$table->coordenadas();                       // latitude, longitude
$table->email('email_contato', true);        // String VARCHAR_DEFAULT
$table->telefone('telefone_contato', true);  // String 15 caracteres
$table->descricao('objeto');                  // String VARCHAR_DEFAULT
$table->observacao();                        // Text
$table->ativo();                             // Boolean default true
$table->status(['ativo', 'inativo'], 'ativo'); // Enum
```

#### Timestamps

```php
$table->datetimes();                         // criado_em, atualizado_em
$table->datetimesWithSoftDeletes();         // criado_em, atualizado_em, excluido_em
```

#### Constantes de Tamanho

```php
$table->string('campo', Blueprint::VARCHAR_TINY);        // 50
$table->string('campo', Blueprint::VARCHAR_SMALL);       // 100
$table->string('campo', Blueprint::VARCHAR_DEFAULT);     // 250
$table->string('campo', Blueprint::VARCHAR_MEDIUM);       // 1000
$table->string('campo', Blueprint::VARCHAR_LARGE);        // 2500
$table->string('campo', Blueprint::VARCHAR_EXTRA_LARGE); // 5000
```

## Exemplos Práticos

### Exemplo 1: Tabela Simples

```php
Schema::create('categorias', function (Blueprint $table) {
    $table->id();
    $table->foreignEmpresa();
    $table->string('nome', Blueprint::VARCHAR_DEFAULT);
    $table->descricao();
    $table->ativo();
    $table->datetimes();
});
```

### Exemplo 2: Tabela com Endereço

```php
Schema::create('fornecedores', function (Blueprint $table) {
    $table->id();
    $table->foreignEmpresa();
    $table->string('razao_social', Blueprint::VARCHAR_DEFAULT);
    $table->string('cnpj', 18)->unique();
    $table->email();
    $table->telefone();
    $table->endereco();
    $table->coordenadas();
    $table->ativo();
    $table->datetimes();
});
```

### Exemplo 3: Tabela com Soft Deletes

```php
Schema::create('processos', function (Blueprint $table) {
    $table->id();
    $table->foreignEmpresa();
    $table->foreignIdCustom('orgao_id', 'orgaos', true);
    $table->string('numero_modalidade', Blueprint::VARCHAR_DEFAULT);
    $table->descricao('objeto_resumido');
    $table->observacao();
    $table->status(['rascunho', 'publicado', 'encerrado'], 'rascunho');
    $table->datetimesWithSoftDeletes();
    
    $table->index('numero_modalidade');
    $table->unique(['empresa_id', 'numero_modalidade']);
});
```

## Organização por Módulos

### Estrutura Recomendada

```
database/migrations/
├── Modules/
│   ├── Processo/
│   │   ├── 2025_01_01_000001_create_processos.php
│   │   └── 2025_01_01_000002_create_processo_itens.php
│   ├── Contrato/
│   │   └── 2025_01_01_000003_create_contratos.php
│   └── Orcamento/
│       └── 2025_01_01_000004_create_orcamentos.php
├── Contexts/
│   ├── Admin/
│   │   └── 2025_01_01_000005_create_admin_logs.php
│   └── Tenant/
│       └── 2025_01_01_000006_create_tenant_settings.php
└── Support/
    └── 2025_01_01_000007_create_functions.php
```

## Executar Migrations

```bash
# Executar todas
php artisan migrate

# Executar com path específico
php artisan migrate --path=database/migrations/Modules/Processo

# Rollback
php artisan migrate:rollback

# Refresh (rollback + migrate)
php artisan migrate:refresh
```

## Boas Práticas

1. **Sempre use a classe base**: `extends Migration`
2. **Use métodos auxiliares**: Evite repetir código
3. **Use constantes de tamanho**: Padronize tamanhos de strings
4. **Organize por módulos**: Facilita manutenção
5. **Nomenclatura clara**: `create_{nome_tabela}.php`
6. **Timestamps em português**: Use `datetimes()` se quiser `criado_em`




