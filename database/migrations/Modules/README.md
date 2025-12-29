# 📁 Estrutura de Migrations por Módulos

## Organização

As migrations devem ser organizadas em pastas por módulo/contexto:

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

## Convenções

### Nomenclatura de Arquivos

- Formato: `{timestamp}_create_{nome_tabela}.php`
- Exemplo: `2025_01_01_000011_create_processo.php`
- **Não usar** `create_table_`, apenas `create_`

### Nomenclatura de Tabelas

Seguir padrão de nomenclatura com prefixos abreviados quando necessário:

- Processos: `processos`
- Contratos: `contratos`
- Orçamentos: `orcamentos`
- etc.

### Nomenclatura de Foreign Keys

- Evitar: `processo_id_id` (repetitivo)
- Preferir: `processo_id` (direto, no mesmo contexto)
- Para relacionamentos: `{tabela}_id` (sem sufixo adicional)

## Exemplo de Migration

```php
<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public string $table = 'processos';

    public function up(): void
    {
        Schema::create('processos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignIdCustom('orgao_id', 'orgaos', true);
            $table->string('numero_modalidade', Blueprint::VARCHAR_DEFAULT);
            $table->enum('status', ['rascunho', 'publicado', 'encerrado'])->default('rascunho');
            $table->descricao('objeto_resumido');
            $table->observacao();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processos');
    }
};
```




