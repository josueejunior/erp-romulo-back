# 🏗️ Estrutura DDD Aplicada

## 📁 Organização das Camadas

```
app/
├── Domain/                          # 🧠 CORAÇÃO DO SISTEMA - Regras de Negócio
│   ├── Tenant/
│   │   ├── Entities/
│   │   │   └── Tenant.php           # Entidade com regras de negócio
│   │   ├── Repositories/
│   │   │   └── TenantRepositoryInterface.php  # Contrato (não sabe de banco)
│   │   └── Services/
│   │       ├── TenantDatabaseServiceInterface.php
│   │       └── TenantRolesServiceInterface.php
│   ├── Empresa/
│   │   ├── Entities/
│   │   │   └── Empresa.php
│   │   └── Repositories/
│   │       └── EmpresaRepositoryInterface.php
│   └── Auth/
│       ├── Entities/
│       │   └── User.php
│       └── Repositories/
│           └── UserRepositoryInterface.php
│
├── Application/                     # 🎯 CASOS DE USO - Orquestração
│   └── Tenant/
│       ├── DTOs/
│       │   └── CriarTenantDTO.php   # Transporta dados entre camadas
│       └── UseCases/
│           └── CriarTenantUseCase.php  # Coordena o fluxo
│
├── Infrastructure/                 # 🔧 DETALHES TÉCNICOS - Implementações
│   ├── Persistence/
│   │   └── Eloquent/
│   │       ├── TenantRepository.php      # Implementa interface com Eloquent
│   │       ├── EmpresaRepository.php
│   │       └── UserRepository.php
│   └── Tenant/
│       ├── TenantDatabaseService.php     # Implementa criação de banco
│       └── TenantRolesService.php        # Implementa roles
│
└── Http/                            # 🌐 ENTRADA - Controllers Finos
    └── Controllers/
        └── Api/
            └── TenantController.php      # Só recebe request e devolve response
```

## 🎯 Princípios Aplicados

### 1. **Controller Fino** ✅
```php
// ❌ ANTES (controller gordo)
class TenantController {
    public function store(Request $request) {
        // 200 linhas de lógica aqui
        $tenant = Tenant::create([...]);
        DB::beginTransaction();
        // ... mais 100 linhas
    }
}

// ✅ DEPOIS (controller fino)
class TenantController {
    public function store(Request $request, CriarTenantUseCase $useCase) {
        $dto = CriarTenantDTO::fromArray($request->validated());
        return $useCase->executar($dto);
    }
}
```

### 2. **Use Case Coordena** ✅
```php
// Application/Tenant/UseCases/CriarTenantUseCase.php
class CriarTenantUseCase {
    public function executar(CriarTenantDTO $dto): array {
        // Coordena tudo, mas não sabe de banco
        $tenant = new Tenant(...);  // Entidade do domínio
        $tenant = $this->repository->criar($tenant);
        $this->databaseService->criarBancoDados($tenant);
        // ...
    }
}
```

### 3. **Domain Pensa** ✅
```php
// Domain/Tenant/Entities/Tenant.php
class Tenant {
    public function podeAlterarCnpj(?string $novoCnpj): bool {
        // Regra de negócio pura
        if ($this->cnpj && $novoCnpj && $novoCnpj !== $this->cnpj) {
            return false;
        }
        return true;
    }
}
```

### 4. **Infrastructure Executa** ✅
```php
// Infrastructure/Persistence/Eloquent/TenantRepository.php
class TenantRepository implements TenantRepositoryInterface {
    public function criar(Tenant $tenant): Tenant {
        // Única camada que conhece Eloquent
        $model = TenantModel::create([...]);
        return $this->toDomain($model);
    }
}
```

## 🔗 Bindings (Dependency Injection)

Registrado em `AppServiceProvider`:

```php
$this->app->bind(
    TenantRepositoryInterface::class,
    TenantRepository::class
);
```

## 📝 Fluxo de Criação de Tenant

```
1. Request → TenantController::store()
   ↓
2. Validação básica (formato)
   ↓
3. Criar DTO
   ↓
4. CriarTenantUseCase::executar()
   ↓
5. Criar entidade Tenant (validações de negócio)
   ↓
6. TenantRepository::criar() (persistência)
   ↓
7. TenantDatabaseService::criarBancoDados()
   ↓
8. TenantRolesService::inicializarRoles()
   ↓
9. EmpresaRepository::criarNoTenant()
   ↓
10. UserRepository::criarAdministrador()
   ↓
11. Response JSON
```

## ✅ Benefícios

1. **Testabilidade**: Cada camada pode ser testada isoladamente
2. **Manutenibilidade**: Mudanças em uma camada não afetam outras
3. **Escalabilidade**: Fácil adicionar novos casos de uso
4. **Legibilidade**: Código expressa o negócio, não a tecnologia
5. **Flexibilidade**: Trocar banco de dados? Só muda Infrastructure

## 🚀 Próximos Passos

1. Migrar outros domínios (Processo, Fornecedor, etc.)
2. Remover código antigo (TenantService, controllers antigos)
3. Adicionar testes unitários para cada camada
4. Documentar Value Objects quando necessário


