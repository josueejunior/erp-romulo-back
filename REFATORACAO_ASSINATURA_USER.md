# Refatoração: Assinatura pertence ao Usuário

## Mudança Arquitetural

A assinatura agora pertence ao **Usuário** em vez do **Tenant**. Isso permite que um usuário tenha uma assinatura e estenda os benefícios para múltiplas empresas/tenants.

## Arquivos Modificados

### 1. Migration
- ✅ `database/migrations/Modules/Assinatura/2026_01_06_140000_add_user_id_to_assinaturas_table.php`
  - Adiciona coluna `user_id` na tabela `assinaturas`

### 2. Entidade de Domínio
- ✅ `app/Domain/Assinatura/Entities/Assinatura.php`
  - Adicionado `userId` como obrigatório
  - `tenantId` agora é opcional

### 3. Modelo Eloquent
- ✅ `app/Modules/Assinatura/Models/Assinatura.php`
  - Adicionado `user_id` no `$fillable`
  - Adicionado relacionamento `user()`

### 4. DTO
- ⚠️ `app/Application/Assinatura/DTOs/CriarAssinaturaDTO.php`
  - Precisa atualizar `fromArray()` para usar `userId`

### 5. Repository
- ⚠️ `app/Infrastructure/Persistence/Eloquent/AssinaturaRepository.php`
  - `toDomain()` atualizado para incluir `userId`
  - `salvar()` precisa incluir `user_id`
  - Adicionar método `buscarAssinaturaAtualPorUsuario(int $userId)`

### 6. Interface do Repository
- ⚠️ `app/Domain/Assinatura/Repositories/AssinaturaRepositoryInterface.php`
  - Adicionar método `buscarAssinaturaAtualPorUsuario(int $userId)`

### 7. Use Cases
- ⚠️ `app/Application/Assinatura/UseCases/CriarAssinaturaUseCase.php`
  - Usar `userId` do DTO em vez de `tenantId`
  
- ⚠️ `app/Application/Assinatura/UseCases/VerificarAssinaturaAtivaUseCase.php`
  - Buscar por `userId` em vez de `tenantId`

### 8. Controllers
- ⚠️ `app/Modules/Payment/Controllers/PaymentController.php`
  - Passar `auth()->id()` como `userId` ao criar assinatura

- ⚠️ `app/Modules/Assinatura/Controllers/AssinaturaController.php`
  - Buscar assinatura do usuário autenticado

### 9. Middleware
- ⚠️ `app/Http/Middleware/CheckSubscription.php`
  - Verificar assinatura do usuário autenticado
  - Validar se o tenant/empresa pertence ao usuário

### 10. Cadastro Público
- ⚠️ `app/Http/Controllers/Public/CadastroPublicoController.php`
  - Passar `userId` do usuário criado ao criar assinatura

## Próximos Passos

1. Executar migration: `php artisan migrate`
2. Atualizar dados existentes: vincular assinaturas aos usuários
3. Testar criação de assinatura
4. Testar verificação de assinatura no middleware

