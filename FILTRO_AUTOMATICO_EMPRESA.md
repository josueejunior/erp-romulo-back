# 🔒 Sistema de Filtro Automático por empresa_id

## ✅ Implementado

Foi implementado um sistema automático de filtro por `empresa_id` seguindo o padrão do sistema de referência, mas adaptado para `empresa_id` em vez de `cliente_id`.

## 🏗️ Arquitetura

### 1. Traits Criados

#### `BelongsToEmpresa` (app/Models/Concerns/BelongsToEmpresa.php)
- Trait para models que pertencem a uma empresa
- Permite que o sistema detecte automaticamente que o model usa `empresa_id`
- Método `getEmpresaField()` retorna `'empresa_id'` por padrão

#### `CheckEmpresaUsage` (app/Services/Traits/CheckEmpresaUsage.php)
- Verifica se um model usa `empresa_id`
- Detecta através de:
  - Trait `BelongsToEmpresa`
  - Método `getEmpresaField()`
  - Campo `empresa_id` no `fillable`

### 2. Classe Base

#### `BaseService` (app/Services/BaseService.php)
- Classe base abstrata para todos os services
- Implementa `IService`
- Aplica filtro automático por `empresa_id` em todas as queries
- Métodos principais:
  - `applyBuilderWhereEmpresa()` - Aplica filtro no builder
  - `createQueryBuilder()` - Cria builder com filtro automático
  - `findById()`, `list()`, `store()`, `update()`, `deleteById()`, `deleteByIds()` - Todos aplicam filtro automaticamente

### 3. Como Funciona

#### A) Detecção Automática
```php
// No Model
use App\Models\Concerns\BelongsToEmpresa;

class Processo extends Model
{
    use BelongsToEmpresa; // Habilita detecção automática
}
```

#### B) Aplicação Automática do Filtro
```php
// No Service
class ProcessoService extends BaseService
{
    protected static string $model = Processo::class;
    
    // O filtro é aplicado AUTOMATICAMENTE
    public function findById(int|string $id, array $params = []): ?Model
    {
        $builder = $this->createQueryBuilder(); // Já tem filtro aplicado
        return $builder->find($id);
    }
}
```

#### C) Integração nos Métodos
Todos os métodos do `BaseService` aplicam o filtro automaticamente:
- `findById()` - Filtra por empresa_id automaticamente
- `list()` - Filtra por empresa_id automaticamente
- `store()` - Adiciona empresa_id automaticamente
- `update()` - Protege empresa_id (não pode ser alterado)
- `deleteById()` - Filtra por empresa_id automaticamente
- `deleteByIds()` - Filtra por empresa_id automaticamente

## 📊 Comparação: Antes vs Depois

### Antes (Manual)
```php
public function findById(int|string $id, array $params = []): ?Model
{
    $query = Processo::query();
    
    // Filtrar por empresa - MANUAL
    if ($empresaId = $params['empresa_id'] ?? $this->getEmpresaId()) {
        $query->where('empresa_id', $empresaId);
    }
    
    return $query->find($id);
}
```

### Depois (Automático)
```php
public function findById(int|string $id, array $params = []): ?Model
{
    // O filtro por empresa_id é aplicado AUTOMATICAMENTE
    $builder = $this->createQueryBuilder();
    return $builder->find($id);
}
```

## 🎯 Vantagens

| Aspecto | Manual | Automático |
|---------|--------|------------|
| **Código** | Repetitivo em cada método | Uma vez no BaseService |
| **Erros** | Fácil esquecer o filtro | Sempre aplicado |
| **Manutenção** | Mudar em vários lugares | Mudar em um lugar |
| **Consistência** | Pode variar | Sempre igual |
| **Segurança** | Risco de vazar dados | Protegido automaticamente |

## 📝 Como Usar

### 1. No Model
```php
use App\Models\Concerns\BelongsToEmpresa;

class Processo extends Model
{
    use BelongsToEmpresa; // Habilita filtro automático
}
```

### 2. No Service
```php
use App\Services\BaseService;

class ProcessoService extends BaseService
{
    protected static string $model = Processo::class;
    
    // Implementar apenas métodos abstratos
    public function validateStoreData(array $data): Validator { }
    public function validateUpdateData(array $data, int|string $id): Validator { }
    
    // Métodos CRUD já estão no BaseService com filtro automático
}
```

### 3. Casos Especiais

#### Desabilitar Filtro (quando necessário)
```php
// Criar query sem filtro
$builder = $this->createQueryBuilder(validateEmpresa: false);
```

#### Filtro Customizado
```php
public function list(array $params = []): LengthAwarePaginator
{
    $builder = $this->createQueryBuilder(); // Filtro automático já aplicado
    
    // Adicionar filtros customizados
    if (isset($params['status'])) {
        $builder->where('status', $params['status']);
    }
    
    return $builder->paginate($params['per_page'] ?? 15);
}
```

## 🔐 Segurança

- **Proteção Automática**: Todos os métodos aplicam filtro automaticamente
- **Sem empresa_id**: Se não houver empresa_id no contexto, retorna query vazia (segurança)
- **Proteção no Update**: `empresa_id` não pode ser alterado (removido automaticamente dos dados)

## 📋 Resumo

✅ **Detecção Automática**: `CheckEmpresaUsage` detecta se model usa `empresa_id`  
✅ **Filtro Automático**: `BaseService` aplica filtro em todas as queries  
✅ **Sem Código Manual**: Não precisa adicionar filtro em cada método  
✅ **Segurança**: Protegido automaticamente contra vazamento de dados  
✅ **Consistência**: Sempre aplicado da mesma forma  

## 🚀 Próximos Passos

1. Aplicar `BelongsToEmpresa` em outros models
2. Migrar outros services para estender `BaseService`
3. Remover filtros manuais de outros services

