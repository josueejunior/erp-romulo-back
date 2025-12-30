# 🏗️ Arquitetura de Controllers e Services

## Visão Geral

Esta arquitetura implementa um padrão padronizado de comunicação entre controllers e services, onde:

- **Controllers** atuam como orquestradores HTTP
- **Services** contêm toda a lógica de negócio
- **Interface IService** padroniza métodos dos services
- **Trait HasDefaultActions** conecta rotas HTTP aos handlers
- **RoutingController** fornece handlers padrão para CRUD

## Componentes

### 1. Interface `IService`

**Localização:** `app/Contracts/IService.php`

Define métodos que services devem implementar:

```php
interface IService
{
    public function createFindByIdParamBag(array $values): array;
    public function findById(int|string $id, array $params = []): ?Model;
    public function createListParamBag(array $values): array;
    public function list(array $params = []): LengthAwarePaginator;
    public function validateStoreData(array $data): Validator;
    public function store(array $data): Model;
    public function validateUpdateData(array $data, int|string $id): Validator;
    public function update(int|string $id, array $data): Model;
    public function deleteById(int|string $id): bool;
    public function deleteByIds(array $ids): int;
}
```

### 2. Trait `HasDefaultActions`

**Localização:** `app/Http/Controllers/Traits/HasDefaultActions.php`

Conecta métodos HTTP aos handlers:

```php
trait HasDefaultActions
{
    public function get(Request $request): JsonResponse;
    public function list(Request $request): JsonResponse;
    public function store(Request $request): JsonResponse;
    public function update(Request $request, int|string $id): JsonResponse;
    public function destroy(Request $request, int|string $id): JsonResponse;
    public function destroyMany(Request $request): JsonResponse;
}
```

### 3. Controller Base `RoutingController`

**Localização:** `app/Http/Controllers/Api/RoutingController.php`

Fornece handlers que chamam os services:

- `handleGet()` → `service->findById()`
- `handleList()` → `service->list()`
- `handleStore()` → `service->store()`
- `handleUpdate()` → `service->update()`
- `handleDestroy()` → `service->deleteById()`
- `handleDestroyMany()` → `service->deleteByIds()`

### 4. Controller Base Simplificado `BaseServiceController`

**Localização:** `app/Http/Controllers/Api/BaseServiceController.php`

Extende `RoutingController` e está pronto para uso com `HasDefaultActions`.

### 5. Helper `RouteHelper`

**Localização:** `app/Helpers/RouteHelper.php`

Registra rotas automaticamente:

```php
RouteHelper::module('processos', ProcessoController::class, 'processo_id');
RouteHelper::nested('processos', 'itens', ProcessoItemController::class);
```

## Fluxo de Execução

```
1. Requisição HTTP chega
   ↓
2. Rota registrada (RouteHelper ou manual)
   ↓
3. Controller recebe (HasDefaultActions::get/list/store/etc)
   ↓
4. RoutingController processa (handleGet/handleList/etc)
   ↓
5. Service é chamado (findById/list/store/etc)
   ↓
6. Service executa lógica de negócio
   ↓
7. Controller formata resposta JSON
```

## Exemplo Completo

### 1. Service

```php
namespace App\Services;

use App\Contracts\IService;
use App\Models\Processo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class ProcessoService implements IService
{
    public function createFindByIdParamBag(array $values): array
    {
        return [
            'with' => $values['with'] ?? [],
            'empresa_id' => $values['empresa_id'] ?? null,
        ];
    }

    public function findById(int|string $id, array $params = []): ?Processo
    {
        $query = Processo::query();
        
        if (isset($params['empresa_id'])) {
            $query->where('empresa_id', $params['empresa_id']);
        }
        
        if (!empty($params['with'])) {
            $query->with($params['with']);
        }
        
        return $query->find($id);
    }

    public function createListParamBag(array $values): array
    {
        return [
            'search' => $values['search'] ?? null,
            'status' => $values['status'] ?? null,
            'page' => $values['page'] ?? 1,
            'per_page' => $values['per_page'] ?? 15,
            'empresa_id' => $values['empresa_id'] ?? null,
        ];
    }

    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Processo::query();
        
        if (isset($params['empresa_id'])) {
            $query->where('empresa_id', $params['empresa_id']);
        }
        
        if (!empty($params['search'])) {
            $query->where('numero_modalidade', 'like', "%{$params['search']}%");
        }
        
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        
        return $query->paginate($params['per_page'] ?? 15);
    }

    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'numero_modalidade' => 'required|string|max:255',
            'orgao_id' => 'required|exists:orgaos,id',
            'empresa_id' => 'required|exists:empresas,id',
            // ... outras regras
        ]);
    }

    public function store(array $data): Processo
    {
        return Processo::create($data);
    }

    public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'numero_modalidade' => 'sometimes|string|max:255',
            'orgao_id' => 'sometimes|exists:orgaos,id',
            // ... outras regras
        ]);
    }

    public function update(int|string $id, array $data): Processo
    {
        $processo = Processo::findOrFail($id);
        $processo->update($data);
        return $processo->fresh();
    }

    public function deleteById(int|string $id): bool
    {
        return Processo::destroy($id) > 0;
    }

    public function deleteByIds(array $ids): int
    {
        return Processo::destroy($ids);
    }
}
```

### 2. Controller

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Traits\HasDefaultActions;
use App\Models\Processo;
use App\Services\ProcessoService;
use Illuminate\Http\Request;

class ProcessoController extends BaseServiceController
{
    use HasDefaultActions;

    protected ?string $storeDataCast = Processo::class;

    public function __construct(protected ProcessoService $service)
    {
        // Laravel injeta automaticamente o ProcessoService
    }
}
```

### 3. Rotas

**Opção 1: Usando RouteHelper**
```php
use App\Helpers\RouteHelper;

RouteHelper::module('processos', ProcessoController::class, 'processo_id', [
    'middleware' => ['auth:sanctum', 'tenancy'],
]);
```

**Opção 2: Manual**
```php
Route::middleware(['auth:sanctum', 'tenancy'])->group(function () {
    Route::get('processos', [ProcessoController::class, 'list']);
    Route::post('processos', [ProcessoController::class, 'store']);
    Route::get('processos/{processo_id}', [ProcessoController::class, 'get']);
    Route::put('processos/{processo_id}', [ProcessoController::class, 'update']);
    Route::delete('processos/{processo_id}', [ProcessoController::class, 'destroy']);
    Route::delete('processos/bulk', [ProcessoController::class, 'destroyMany']);
});
```

## Recursos Aninhados

Para recursos aninhados (ex: itens dentro de processos):

```php
// Controller
class ProcessoItemController extends BaseServiceController
{
    use HasDefaultActions;

    protected ?array $routeParentIdBinding = [
        'parameter' => 'processo_id',
        'inject' => 'params', // ou 'argument'
    ];

    public function __construct(protected ProcessoItemService $service) {}
}

// Rotas
RouteHelper::nested('processos', 'itens', ProcessoItemController::class);
```

## Vantagens

1. **Separação de Responsabilidades**: Controller lida com HTTP, Service com lógica
2. **Reutilização**: Services podem ser usados em múltiplos controllers
3. **Testabilidade**: Services podem ser testados independentemente
4. **Padronização**: Todos os controllers seguem o mesmo padrão
5. **Injeção Automática**: Laravel resolve dependências automaticamente
6. **Menos Código**: Controllers ficam muito menores

## Migração

Para migrar um controller existente:

1. Criar service implementando `IService`
2. Mover lógica do controller para o service
3. Controller herda `BaseServiceController` e usa `HasDefaultActions`
4. Injetar service via construtor
5. Registrar rotas com `RouteHelper` ou manualmente

## Próximos Passos

- [ ] Criar services para controllers existentes
- [ ] Migrar controllers para usar `BaseServiceController`
- [ ] Aplicar `RouteHelper` nas rotas
- [ ] Adicionar testes para services





