# ✅ Arquitetura de Migrations Aplicada

## Componentes Criados

### 1. ✅ Classe Base `Migration`
**Localização:** `app/Database/Migrations/Migration.php`
- Estende `Illuminate\Database\Migrations\Migration`
- Propriedade `$table` para nome da tabela
- Método `getTableName()` para obter nome da tabela

### 2. ✅ Blueprint Customizado
**Localização:** `app/Database/Schema/Blueprint.php`
- Estende `Illuminate\Database\Schema\Blueprint`
- Constantes de tamanho (VARCHAR_TINY, VARCHAR_SMALL, etc.)
- Timestamps em português (criado_em, atualizado_em, excluido_em)
- Métodos auxiliares para Foreign Keys
- Métodos auxiliares para campos comuns

### 3. ✅ DatabaseServiceProvider
**Localização:** `app/Providers/DatabaseServiceProvider.php`
- Carrega migrations recursivamente de todas as pastas
- Ordena por timestamp para manter ordem de execução
- Registrado em `bootstrap/providers.php`

### 4. ✅ SchemaServiceProvider
**Localização:** `app/Providers/SchemaServiceProvider.php`
- Placeholder para futuras configurações de Schema
- Registrado em `bootstrap/providers.php`

### 5. ✅ Configuração
**Localização:** `config/database.php`
- Tabela de migrations: `_migrations` (não `migrations`)

## Como Usar

### Em uma Migration

```php
<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'minha_tabela';

    public function up(): void
    {
        Schema::create('minha_tabela', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->string('nome', Blueprint::VARCHAR_DEFAULT);
            $table->descricao();
            $table->ativo();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minha_tabela');
    }
};
```

## Métodos Disponíveis

### Foreign Keys
- `$table->foreignEmpresa($nullable)`
- `$table->foreignTenant($nullable)`
- `$table->foreignUsuario($nullable)`
- `$table->foreignPessoa($nullable)`
- `$table->foreignIdCustom($column, $table, $nullable, $onDelete)`

### Campos Comuns
- `$table->endereco()` - Cria campos de endereço completo
- `$table->coordenadas()` - latitude, longitude
- `$table->email($column, $nullable)`
- `$table->telefone($column, $nullable)`
- `$table->descricao($column, $nullable)`
- `$table->observacao($column, $nullable)`
- `$table->ativo($column)`
- `$table->status($values, $default)`

### Timestamps
- `$table->datetimes()` - criado_em, atualizado_em
- `$table->datetimesWithSoftDeletes()` - + excluido_em

### Constantes
- `Blueprint::VARCHAR_TINY = 50`
- `Blueprint::VARCHAR_SMALL = 100`
- `Blueprint::VARCHAR_DEFAULT = 250`
- `Blueprint::VARCHAR_MEDIUM = 1000`
- `Blueprint::VARCHAR_LARGE = 2500`
- `Blueprint::VARCHAR_EXTRA_LARGE = 5000`

## Estrutura de Pastas

As migrations podem ser organizadas em:
```
database/migrations/
├── Modules/          # Módulos principais
├── Contexts/         # Contextos específicos
└── Support/         # Funções e triggers
```

O `DatabaseServiceProvider` carrega todas automaticamente, ordenadas por timestamp.

## Status

✅ **Arquitetura aplicada e pronta para uso!**

Todas as migrations futuras devem usar:
- `App\Database\Migrations\Migration` como classe base
- `App\Database\Schema\Blueprint` no Schema::create/table
- Métodos auxiliares do Blueprint
- Constantes de tamanho padronizadas

