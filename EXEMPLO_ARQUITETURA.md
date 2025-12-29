# 📝 Exemplo Prático: Aplicando a Arquitetura

## Exemplo: Controller de Fornecedores

### 1. Service Implementando IService

```php
<?php

namespace App\Services;

use App\Contracts\IService;
use App\Models\Fornecedor;
use App\Services\Traits\AuthScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class FornecedorService implements IService
{
    use AuthScope;

    public function createFindByIdParamBag(array $values): array
    {
        return [
            'with' => $values['with'] ?? [],
            'empresa_id' => $values['empresa_id'] ?? $this->getEmpresaId(),
        ];
    }

    public function findById(int|string $id, array $params = []): ?Fornecedor
    {
        $query = Fornecedor::query();
        
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
            'page' => $values['page'] ?? 1,
            'per_page' => $values['per_page'] ?? 15,
            'empresa_id' => $values['empresa_id'] ?? $this->getEmpresaId(),
        ];
    }

    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Fornecedor::query();
        
        if (isset($params['empresa_id'])) {
            $query->where('empresa_id', $params['empresa_id']);
        }
        
        if (!empty($params['search'])) {
            $query->where(function($q) use ($params) {
                $q->where('razao_social', 'like', "%{$params['search']}%")
                  ->orWhere('cnpj', 'like', "%{$params['search']}%")
                  ->orWhere('nome_fantasia', 'like', "%{$params['search']}%");
            });
        }
        
        return $query->orderBy('razao_social')->paginate($params['per_page'] ?? 15);
    }

    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18|unique:fornecedores,cnpj',
            'nome_fantasia' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'empresa_id' => 'required|exists:empresas,id',
        ]);
    }

    public function store(array $data): Fornecedor
    {
        // Garantir empresa_id se não fornecido
        if (!isset($data['empresa_id'])) {
            $data['empresa_id'] = $this->getEmpresaId();
        }
        
        return Fornecedor::create($data);
    }

    public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'razao_social' => 'sometimes|string|max:255',
            'cnpj' => "nullable|string|max:18|unique:fornecedores,cnpj,{$id}",
            'nome_fantasia' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
        ]);
    }

    public function update(int|string $id, array $data): Fornecedor
    {
        $fornecedor = Fornecedor::findOrFail($id);
        
        // Validar que pertence à empresa do usuário
        if (isset($data['empresa_id']) && $fornecedor->empresa_id !== $data['empresa_id']) {
            abort(403, 'Fornecedor não pertence à sua empresa');
        }
        
        $fornecedor->update($data);
        return $fornecedor->fresh();
    }

    public function deleteById(int|string $id): bool
    {
        $fornecedor = Fornecedor::findOrFail($id);
        
        // Validar que pertence à empresa do usuário
        $empresaId = $this->getEmpresaId();
        if ($fornecedor->empresa_id !== $empresaId) {
            abort(403, 'Fornecedor não pertence à sua empresa');
        }
        
        return $fornecedor->delete();
    }

    public function deleteByIds(array $ids): int
    {
        $empresaId = $this->getEmpresaId();
        
        return Fornecedor::whereIn('id', $ids)
            ->where('empresa_id', $empresaId)
            ->delete();
    }
}
```

### 2. Controller Simplificado

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Traits\HasDefaultActions;
use App\Models\Fornecedor;
use App\Services\FornecedorService;

class FornecedorController extends BaseServiceController
{
    use HasDefaultActions;

    protected ?string $storeDataCast = Fornecedor::class;

    public function __construct(protected FornecedorService $service)
    {
        // Laravel injeta automaticamente o FornecedorService
    }
}
```

### 3. Registrar Rotas

**Opção 1: Usando RouteHelper**
```php
use App\Helpers\RouteHelper;

Route::middleware(['auth:sanctum', 'tenancy'])->group(function () {
    RouteHelper::module('fornecedores', FornecedorController::class, 'fornecedor_id');
});
```

**Opção 2: Manual**
```php
Route::middleware(['auth:sanctum', 'tenancy'])->group(function () {
    Route::get('fornecedores', [FornecedorController::class, 'list']);
    Route::post('fornecedores', [FornecedorController::class, 'store']);
    Route::get('fornecedores/{fornecedor_id}', [FornecedorController::class, 'get']);
    Route::put('fornecedores/{fornecedor_id}', [FornecedorController::class, 'update']);
    Route::delete('fornecedores/{fornecedor_id}', [FornecedorController::class, 'destroy']);
    Route::delete('fornecedores/bulk', [FornecedorController::class, 'destroyMany']);
});
```

## Comparação: Antes vs Depois

### Antes (Controller com lógica)

```php
class FornecedorController extends BaseApiController
{
    public function index(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        $query = Fornecedor::where('empresa_id', $empresa->id);
        
        if ($request->search) {
            $query->where('razao_social', 'like', "%{$request->search}%");
        }
        
        return response()->json($query->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'razao_social' => 'required|string|max:255',
            // ... mais validações
        ]);
        
        $empresa = $this->getEmpresaAtivaOrFail();
        $validated['empresa_id'] = $empresa->id;
        
        $fornecedor = Fornecedor::create($validated);
        
        return response()->json($fornecedor, 201);
    }
    
    // ... mais métodos
}
```

### Depois (Controller delegando ao service)

```php
class FornecedorController extends BaseServiceController
{
    use HasDefaultActions;

    public function __construct(protected FornecedorService $service) {}
}
```

**Redução de código:** ~200 linhas → ~10 linhas

## Recursos Aninhados

Exemplo: Itens dentro de Processos

```php
// Service
class ProcessoItemService implements IService
{
    // ... implementação
}

// Controller
class ProcessoItemController extends BaseServiceController
{
    use HasDefaultActions;

    protected ?array $routeParentIdBinding = [
        'parameter' => 'processo_id',
        'inject' => 'params',
    ];

    public function __construct(protected ProcessoItemService $service) {}
}

// Rotas
RouteHelper::nested('processos', 'itens', ProcessoItemController::class);
```

Isso cria rotas:
- `GET /processos/{processo_id}/itens` → list()
- `POST /processos/{processo_id}/itens` → store()
- `GET /processos/{processo_id}/itens/{id}` → get()
- etc.

O `processo_id` será automaticamente injetado nos parâmetros do service.

## Vantagens da Arquitetura

1. **Controllers mínimos**: Apenas 5-10 linhas
2. **Lógica centralizada**: Toda lógica no service
3. **Testabilidade**: Services testáveis independentemente
4. **Reutilização**: Services podem ser usados em múltiplos lugares
5. **Padronização**: Todos seguem o mesmo padrão
6. **Manutenibilidade**: Fácil de entender e modificar




