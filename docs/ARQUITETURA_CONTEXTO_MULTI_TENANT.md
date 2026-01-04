# Arquitetura de Contexto Multi-Tenant - Análise e Melhorias

## Pontos Fracos Identificados (ANTES)

### 1. **Ordem de Middlewares Inconsistente**
- `EnsureEmpresaAtivaContext` roda como middleware GLOBAL antes da autenticação
- Quando executa, `Auth::check()` retorna `false` porque o `auth:sanctum` ainda não rodou
- Resultado: O contexto NUNCA é setado corretamente

### 2. **Contexto Setado em Múltiplos Lugares**
- `TenantContext::set()` era chamado em 3+ middlewares diferentes
- Cada middleware tinha sua própria lógica de fallback
- Inconsistência e duplicação de código

### 3. **Fallbacks Espalhados nos UseCases**
- Cada UseCase tinha código duplicado para resolver `empresa_id`:
  ```php
  $empresaId = $dto->empresaId > 0 
      ? $dto->empresaId 
      : ($context->empresaId ?? app('current_empresa_id') ?? 0);
  ```

### 4. **Uso Incorreto de `method_exists`**
- `method_exists($user, 'empresa_ativa_id')` sempre retornava `false`
- `empresa_ativa_id` é um ATRIBUTO do Eloquent, não um método

### 5. **Dependência de Headers Opcionais**
- Sistema dependia do frontend enviar `X-Empresa-ID` e `X-Tenant-ID`
- Sem fallbacks robustos quando headers não enviados

---

## Solução Implementada

### 1. **ApplicationContext Service (Singleton)**
Arquivo: `app/Services/ApplicationContext.php`

Serviço centralizado que é o **único ponto de verdade** para:
- `tenant_id`
- `empresa_id`
- `user`
- `tenant` (model)
- `empresa` (model)

```php
// Uso simples em qualquer lugar
$context = ApplicationContext::current();
$empresaId = $context->getEmpresaId();
$tenantId = $context->getTenantId();
```

### 2. **Trait HasApplicationContext**
Arquivo: `app/Application/Shared/Traits/HasApplicationContext.php`

Trait para UseCases que fornece resolução robusta de `empresa_id`:

```php
class CriarFornecedorUseCase
{
    use HasApplicationContext;
    
    public function executar(CriarFornecedorDTO $dto): Fornecedor
    {
        // Resolve com fallbacks automáticos
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        // ...
    }
}
```

**Prioridade de resolução:**
1. Valor do DTO (se > 0)
2. ApplicationContext
3. TenantContext (compatibilidade)
4. Container 'current_empresa_id' (legado)

### 3. **Integração no InitializeTenancyByRequestData**
O middleware agora:
1. Inicializa o tenant (multi-tenancy)
2. Inicializa o ApplicationContext
3. Sincroniza com TenantContext (compatibilidade)
4. Disponibiliza no container

### 4. **Correção do EnsureEmpresaAtivaContext**
- Removido `method_exists($user, 'empresa_ativa_id')` incorreto
- Adicionados logs de debug para diagnóstico

---

## Arquivos Criados/Modificados

### Novos Arquivos:
- `app/Services/ApplicationContext.php` - Serviço singleton centralizado
- `app/Application/Shared/Traits/HasApplicationContext.php` - Trait para UseCases
- `app/Http/Middleware/InitializeApplicationContext.php` - Middleware unificado (para migração futura)

### Arquivos Modificados:
- `app/Providers/AppServiceProvider.php` - Registro do singleton
- `app/Http/Middleware/InitializeTenancyByRequestData.php` - Integração com ApplicationContext
- `app/Http/Middleware/EnsureEmpresaAtivaContext.php` - Correção de bugs e logs
- `app/Application/Fornecedor/UseCases/CriarFornecedorUseCase.php` - Usa o trait
- `app/Application/Orgao/UseCases/CriarOrgaoUseCase.php` - Usa o trait
- `app/Application/CustoIndireto/UseCases/CriarCustoIndiretoUseCase.php` - Usa o trait
- `app/Application/AutorizacaoFornecimento/UseCases/CriarAutorizacaoFornecimentoUseCase.php` - Usa o trait
- `app/Application/Orcamento/UseCases/CriarOrcamentoUseCase.php` - Usa o trait
- `app/Application/Processo/UseCases/CriarProcessoUseCase.php` - Usa o trait
- `app/Application/Contrato/UseCases/CriarContratoUseCase.php` - Usa o trait

---

## Próximos Passos (Migração Gradual)

1. **Deploy das correções atuais** - Resolve o problema imediato

2. **Migração para InitializeApplicationContext** (opcional):
   - Substituir `EnsureEmpresaAtivaContext` + parte do `InitializeTenancyByRequestData`
   - Um único middleware que faz tudo

3. **Atualizar outros UseCases** para usar o trait:
   - Buscar por `TenantContext::get()` e substituir pelo trait

4. **Adicionar validação de acesso**:
   - Verificar se `empresa_id` pertence ao `tenant_id`
   - Verificar se usuário tem acesso à empresa

5. **Testes automatizados**:
   - Testar resolução de contexto em diferentes cenários
   - Testar fallbacks

---

## Diagrama de Fluxo (DEPOIS)

```
Request
   │
   ▼
[auth:sanctum] ─────────────────────────────────────────┐
   │                                                     │
   ▼                                                     │
[InitializeTenancyByRequestData]                        │
   │                                                     │
   ├─► Resolver tenant_id (header/token/user)           │
   │                                                     │
   ├─► tenancy()->initialize($tenant)                   │
   │                                                     │
   ├─► ApplicationContext::initialize()                 │
   │       │                                             │
   │       ├─► Resolver empresa_id                      │
   │       │     1. Header X-Empresa-ID                 │
   │       │     2. user.empresa_ativa_id               │
   │       │     3. Primeira empresa do user            │
   │       │                                             │
   │       └─► Setar no container                       │
   │                                                     │
   └─► TenantContext::set(tenant_id, empresa_id)        │
                                                         │
   ▼                                                     │
[Controller]                                             │
   │                                                     │
   ▼                                                     │
[UseCase com HasApplicationContext]                      │
   │                                                     │
   └─► $this->resolveEmpresaId() ◄───────────────────────┘
           │
           ├─► 1. DTO
           ├─► 2. ApplicationContext
           ├─► 3. TenantContext
           └─► 4. Container
```
