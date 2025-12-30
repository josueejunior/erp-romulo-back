# 🏗️ Estrutura de Módulos - Proposta

## 📋 O que será feito

Organizar o código atual em módulos funcionais, movendo arquivos e atualizando namespaces.

## 🎯 Estrutura Proposta

### Antes (Estrutura Plana)
```
app/
├── Models/
│   ├── Processo.php
│   ├── ProcessoItem.php
│   └── ...
├── Services/
│   ├── ProcessoStatusService.php
│   └── ...
├── Http/Controllers/Api/
│   ├── ProcessoController.php
│   └── ...
└── ...
```

### Depois (Estrutura Modular)
```
app/
├── Modules/
│   ├── Processo/
│   │   ├── Models/
│   │   │   ├── Processo.php
│   │   │   ├── ProcessoItem.php
│   │   │   ├── ProcessoDocumento.php
│   │   │   └── ProcessoItemVinculo.php
│   │   ├── Services/
│   │   │   ├── ProcessoStatusService.php
│   │   │   ├── ProcessoValidationService.php
│   │   │   ├── SaldoService.php
│   │   │   ├── DisputaService.php
│   │   │   └── ExportacaoService.php
│   │   ├── Controllers/
│   │   │   ├── ProcessoController.php
│   │   │   ├── ProcessoItemController.php
│   │   │   ├── DisputaController.php
│   │   │   ├── JulgamentoController.php
│   │   │   ├── SaldoController.php
│   │   │   └── ExportacaoController.php
│   │   ├── Resources/
│   │   │   ├── ProcessoResource.php
│   │   │   ├── ProcessoListResource.php
│   │   │   └── ProcessoItemResource.php
│   │   ├── Observers/
│   │   │   └── ProcessoObserver.php
│   │   └── Policies/
│   │       └── ProcessoPolicy.php
│   │
│   ├── Orcamento/
│   ├── Contrato/
│   └── ...
│
├── Shared/
│   ├── Contracts/
│   ├── Database/
│   ├── Helpers/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── BaseApiController.php
│   │   │   └── BaseServiceController.php
│   │   └── Middleware/
│   └── Services/
│       ├── RedisService.php
│       └── FinanceiroService.php
│
└── Admin/
    └── Controllers/
```

## 📝 Mudanças de Namespace

### Models
```php
// Antes
namespace App\Models;
class Processo extends Model { }

// Depois
namespace App\Modules\Processo\Models;
class Processo extends Model { }
```

### Services
```php
// Antes
namespace App\Services;
use App\Models\Processo;

// Depois
namespace App\Modules\Processo\Services;
use App\Modules\Processo\Models\Processo;
```

### Controllers
```php
// Antes
namespace App\Http\Controllers\Api;
use App\Models\Processo;
use App\Services\ProcessoStatusService;

// Depois
namespace App\Modules\Processo\Controllers;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Services\ProcessoStatusService;
```

## ⚠️ Impacto

### Arquivos que precisarão atualizar imports:
- ✅ Rotas (`routes/api.php`)
- ✅ Service Providers (`AppServiceProvider.php`)
- ✅ Outros Controllers que usam Processo
- ✅ Outros Services que usam Processo
- ✅ Tests (se existirem)

## 🚀 Abordagem

1. **Fase 1**: Mover arquivos do módulo Processo (piloto)
2. **Fase 2**: Atualizar namespaces nos arquivos movidos
3. **Fase 3**: Atualizar imports em todos os arquivos que referenciam Processo
4. **Fase 4**: Testar e validar
5. **Fase 5**: Repetir para outros módulos

## ❓ Decisões Necessárias

1. **Manter compatibilidade?** 
   - Opção A: Criar aliases/forwarding classes em `app/Models/` que apontam para `app/Modules/`
   - Opção B: Atualizar tudo de uma vez (mais limpo, mas mais trabalho)

2. **Ordem de migração?**
   - Começar com Processo (mais complexo) ou com módulo mais simples?

3. **Shared vs Modules?**
   - Onde colocar código compartilhado? (RedisService, FinanceiroService, etc.)

**Qual abordagem você prefere?**





