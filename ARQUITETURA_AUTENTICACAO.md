# 🔐 Arquitetura de Autenticação e Contexto

## Visão Geral

Esta arquitetura implementa um sistema padronizado de acesso ao contexto de autenticação, similar ao exemplo fornecido, permitindo que controllers e services acessem dados do usuário, tenant e empresa de forma consistente.

## Componentes

### 1. Interface `IAuthIdentity`

**Localização:** `app/Contracts/IAuthIdentity.php`

Interface que define o contrato para acessar dados do usuário autenticado:

```php
interface IAuthIdentity
{
    public function getUserId(): ?int;
    public function getTenantId(): ?string;
    public function getEmpresaId(): ?int;
    public function getUser(): ?Authenticatable;
    public function getTenant(): ?Tenant;
    public function getEmpresa(): ?Empresa;
    public function isAdminCentral(): bool;
    public function isTenantUser(): bool;
    public function getScope(): string;
}
```

### 2. Service `AuthIdentityService`

**Localização:** `app/Services/AuthIdentityService.php`

Service responsável por criar instâncias de `IAuthIdentity` baseadas no contexto:

- **`TenantAuthIdentity`**: Para usuários de tenants
- **`AdminAuthIdentity`**: Para administradores centrais
- **`NullAuthIdentity`**: Para requisições não autenticadas

### 3. Middleware `SetAuthContext`

**Localização:** `app/Http/Middleware/SetAuthContext.php`

Middleware que:
1. Autentica o usuário via `auth('sanctum')->check()`
2. Define o escopo na requisição (`$request->scope`)
3. Cria a identidade de autenticação
4. Armazena no container Laravel via `app()->instance(IAuthIdentity::class, $identity)`

**Uso nas rotas:**
```php
Route::middleware(['auth:sanctum', SetAuthContext::class, 'tenancy'])->group(function () {
    // Rotas do tenant
});

Route::middleware(['auth:sanctum', SetAuthContext::class . ':admin', IsSuperAdmin::class])->group(function () {
    // Rotas do admin
});
```

### 4. Trait `HasAuthContext`

**Localização:** `app/Http/Controllers/Traits/HasAuthContext.php`

Trait para controllers e services novos que precisam acessar o contexto:

```php
use HasAuthContext;

// Métodos disponíveis:
$this->getUserId();        // ID do usuário
$this->getTenantId();      // ID do tenant
$this->getEmpresaId();     // ID da empresa
$this->getUser();          // Objeto do usuário
$this->getTenant();        // Objeto do tenant
$this->getEmpresa();       // Objeto da empresa
$this->isAdminCentral();   // Verifica se é admin
$this->isTenantUser();     // Verifica se é usuário do tenant
$this->getScope();         // Escopo de autenticação

// Métodos que lançam exceção se não encontrado:
$this->getUserOrFail();
$this->getEmpresaOrFail();
$this->getTenantOrFail();
```

### 5. Trait `AuthScope`

**Localização:** `app/Services/Traits/AuthScope.php`

Trait para services Legacy que precisam de compatibilidade:

```php
use AuthScope;

// Métodos compatíveis com código legado:
$this->getClienteId();     // Alias para getTenantId()
$this->getEmpresaId();     // ID da empresa
$this->getUserId();        // ID do usuário
$this->auth($guard);       // Guard de autenticação
$this->session();          // Sessão atual
$this->getCurrentUser();   // Usuário autenticado
```

## Fluxo de Autenticação

```
1. Requisição chega
   ↓
2. Middleware SetAuthContext
   ↓
3. auth('sanctum')->check() autentica
   ↓
4. AuthIdentityService cria identidade
   ↓
5. Identidade armazenada no container Laravel
   ↓
6. Controller/Service acessa via traits
   ↓
7. Métodos do trait retornam dados do usuário/tenant/empresa
```

## Exemplos de Uso

### Em um Controller

```php
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasAuthContext;

class MeuController extends Controller
{
    use HasAuthContext;

    public function index()
    {
        $userId = $this->getUserId();
        $empresa = $this->getEmpresaOrFail();
        $tenant = $this->getTenant();
        
        // Usar os dados...
    }
}
```

### Em um Service

```php
use App\Services\Traits\AuthScope;

class MeuService
{
    use AuthScope;

    public function fazerAlgo()
    {
        $clienteId = $this->getClienteId(); // Compatível com código legado
        $empresaId = $this->getEmpresaId();
        $user = $this->getCurrentUser();
        
        // Usar os dados...
    }
}
```

### Verificando Tipo de Usuário

```php
if ($this->isAdminCentral()) {
    // Lógica para admin central
} elseif ($this->isTenantUser()) {
    // Lógica para usuário do tenant
    $empresa = $this->getEmpresaOrFail();
}
```

## Integração com Código Existente

### BaseApiController

O `BaseApiController` agora usa o trait `HasAuthContext` e mantém os métodos legados para compatibilidade:

- `getEmpresaAtiva()` - Deprecated, use `getEmpresa()`
- `getEmpresaAtivaOrFail()` - Deprecated, use `getEmpresaOrFail()`

### Services

Services podem usar `AuthScope` para compatibilidade com código legado ou `HasAuthContext` para código novo.

## Benefícios

1. **Padronização**: Acesso consistente aos dados de autenticação
2. **Testabilidade**: Identidade pode ser mockada facilmente
3. **Flexibilidade**: Suporta diferentes tipos de usuários (admin, tenant)
4. **Compatibilidade**: Traits separados para código novo e legado
5. **Manutenibilidade**: Lógica centralizada no middleware e service

## Migração

Para migrar código existente:

1. **Controllers**: Adicione `use HasAuthContext;` e substitua `auth()->user()` por `$this->getUser()`
2. **Services**: Adicione `use AuthScope;` ou `use HasAuthContext;` conforme necessário
3. **Métodos legados**: Mantidos para compatibilidade, mas marcados como `@deprecated`

## Próximos Passos

- [ ] Aplicar `HasAuthContext` em todos os controllers novos
- [ ] Migrar services para usar `AuthScope` ou `HasAuthContext`
- [ ] Remover métodos deprecados após migração completa
- [ ] Adicionar testes unitários para `AuthIdentityService`





