# 🔗 TenantModel - Modelo Base para Tabelas do Tenant

## Visão Geral

O `TenantModel` é uma classe base que estende `BaseModel` e automaticamente gerencia a conexão com o banco do tenant. Use esta classe para todos os modelos cujas tabelas estão no banco do tenant, não no banco central.

## Quando Usar

### ✅ Use `TenantModel` para:
- Modelos cujas tabelas estão no banco do tenant (ex: `processos`, `empresas`, `orgaos`, `fornecedores`, etc.)
- Modelos que pertencem ao domínio do tenant
- Modelos que são isolados por tenant

### ❌ NÃO use `TenantModel` para:
- Modelos do banco central (ex: `tenants`, `planos`, `onboarding_progress`, `users_lookup`, etc.)
- Modelos que devem sempre usar a conexão `pgsql` (banco central)

## Exemplo de Uso

```php
<?php

namespace App\Modules\Processo\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Processo extends TenantModel
{
    protected $fillable = [
        'empresa_id',
        'orgao_id',
        // ...
    ];

    // Não precisa definir getConnectionName() - já está implementado no TenantModel
    // O modelo automaticamente usará a conexão 'tenant' quando o tenancy estiver inicializado
}
```

## Como Funciona

O `TenantModel` implementa o método `getConnectionName()` que:

1. **Verifica a conexão padrão**: Se já for `tenant`, retorna `tenant`
2. **Verifica o tenancy**: Se o tenancy estiver inicializado, retorna `tenant`
3. **Fallback**: Retorna `null` para usar a conexão padrão

Isso garante que:
- ✅ Modelos de tenant sempre usam o banco correto
- ✅ Não há necessidade de definir `protected $connection` em cada modelo
- ✅ Funciona automaticamente quando o tenancy é inicializado

## Modelos Atualizados

Os seguintes modelos já foram atualizados para usar `TenantModel`:

- ✅ `App\Modules\Processo\Models\Processo`
- ✅ `App\Modules\Processo\Models\ProcessoItem`
- ✅ `App\Modules\Processo\Models\ProcessoDocumento`
- ✅ `App\Modules\Processo\Models\ProcessoItemVinculo`

## Migração de Modelos Existentes

Para migrar um modelo existente de `BaseModel` para `TenantModel`:

1. **Altere o import**:
   ```php
   // Antes
   use App\Models\BaseModel;
   
   // Depois
   use App\Models\TenantModel;
   ```

2. **Altere a declaração da classe**:
   ```php
   // Antes
   class MeuModel extends BaseModel
   
   // Depois
   class MeuModel extends TenantModel
   ```

3. **Remova qualquer método `getConnectionName()` personalizado** (se houver)

## Modelos do Banco Central

Modelos que devem **permanecer** usando `BaseModel` ou ter `protected $connection = 'pgsql'`:

- `App\Models\Tenant` - Tabela de tenants (banco central)
- `App\Modules\Assinatura\Models\Plano` - Planos (banco central)
- `App\Models\OnboardingProgress` - Progresso de onboarding (banco central)
- `App\Models\UserLookup` - Lookup de usuários (banco central)
- `App\Models\TenantEmpresa` - Relação tenant-empresa (banco central)
- `App\Modules\Auth\Models\AdminUser` - Usuários admin (banco central)

## Benefícios

1. **Consistência**: Todos os modelos de tenant usam a mesma lógica de conexão
2. **Manutenibilidade**: Mudanças na lógica de conexão são feitas em um único lugar
3. **Segurança**: Garante que modelos de tenant sempre usam o banco correto
4. **Simplicidade**: Não precisa definir `getConnectionName()` em cada modelo


