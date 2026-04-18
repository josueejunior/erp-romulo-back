# Refatoração: Middleware de Autenticação e Bootstrap

## Problema Identificado

O sistema estava travando na cadeia de middlewares, especificamente após `HandleApiErrors` chamar `$next($request)`. O middleware `auth:sanctum` não estava sendo executado, causando travamentos de ~30 segundos.

### Sintomas:
- Requisições travando após `HandleApiErrors`
- Middleware `auth:sanctum` não aparecendo nos logs
- `SetAuthContext` e `EnsureEmpresaAtivaContext` não sendo executados
- Timeout de requisições

## Solução Implementada

### Middleware Unificado: `AuthenticateAndBootstrap`

Criado um middleware único que consolida todas as responsabilidades:

1. **Autenticação Sanctum** - Verifica e autentica o usuário
2. **Criação de Identidade** - Cria `IAuthIdentity` para o container
3. **Bootstrap do Contexto** - Inicializa tenancy, empresa, etc.
4. **Continuação da Requisição** - Chama `$next($request)`

### Vantagens:

✅ **Simplicidade**: Um único middleware em vez de 3 separados
✅ **Confiabilidade**: Evita problemas de travamento entre middlewares
✅ **Rastreabilidade**: Logs centralizados e detalhados
✅ **Manutenibilidade**: Lógica consolidada em um único lugar
✅ **Performance**: Menos overhead de múltiplos middlewares

## Mudanças Realizadas

### 1. Novo Middleware: `AuthenticateAndBootstrap`

**Localização**: `app/Http/Middleware/AuthenticateAndBootstrap.php`

**Responsabilidades**:
- Autentica via `auth('sanctum')->check()`
- Cria identidade via `AuthIdentityService`
- Inicializa contexto via `ApplicationContext::bootstrap()`
- Logs detalhados para diagnóstico

### 2. Atualização das Rotas

**Antes**:
```php
Route::middleware([
    'auth:sanctum', 
    \App\Http\Middleware\SetAuthContext::class, 
    \App\Http\Middleware\EnsureEmpresaAtivaContext::class, 
    'throttle:120,1'
])->group(function () {
    // rotas...
});
```

**Depois**:
```php
Route::middleware([
    \App\Http\Middleware\AuthenticateAndBootstrap::class, 
    'throttle:120,1'
])->group(function () {
    // rotas...
});
```

## Fluxo de Execução

```
1. HandleCorsCustom (global)
   ↓
2. HandleApiErrors (global API)
   ↓
3. AuthenticateAndBootstrap (rota)
   ├─ Verifica autenticação Sanctum
   ├─ Cria identidade de autenticação
   ├─ Bootstrap do ApplicationContext
   │  ├─ Resolve empresa_id
   │  ├─ Resolve tenant_id
   │  └─ Inicializa tenancy
   └─ Chama $next($request)
   ↓
4. Controller
```

## Logs Esperados

Após o deploy, você deve ver:

```
[INFO] AuthenticateAndBootstrap::handle - ✅ INÍCIO
[DEBUG] AuthenticateAndBootstrap::handle - Verificando autenticação Sanctum
[DEBUG] AuthenticateAndBootstrap::handle - Usuário autenticado {"user_id":1}
[DEBUG] AuthenticateAndBootstrap::handle - Criando identidade de autenticação
[DEBUG] AuthenticateAndBootstrap::handle - Identidade criada {"elapsed_time":"0.008s"}
[INFO] AuthenticateAndBootstrap::handle - Iniciando bootstrap do ApplicationContext
[INFO] ApplicationContext::bootstrap() - Iniciando bootstrap
[INFO] AuthenticateAndBootstrap::handle - Bootstrap concluído {"elapsed_time":"0.015s"}
[DEBUG] AuthenticateAndBootstrap::handle - Chamando $next($request)
[INFO] AuthenticateAndBootstrap::handle - ✅ FIM {"status":201,"total_elapsed_time":"0.025s"}
```

## Middlewares Mantidos (Compatibilidade)

Os middlewares antigos foram mantidos para compatibilidade, mas não são mais usados nas rotas principais:

- `SetAuthContext` - Mantido para uso em rotas específicas se necessário
- `EnsureEmpresaAtivaContext` - Mantido para uso em rotas específicas se necessário
- `InitializeTenancyByRequestData` - Mantido mas não usado

## Rollback

Se necessário fazer rollback:

1. Reverter `routes/api.php` para usar os middlewares antigos
2. Remover `AuthenticateAndBootstrap.php`
3. Limpar cache: `php artisan route:clear && php artisan config:clear`

## Próximos Passos

1. ✅ Fazer deploy do código atualizado
2. ✅ Limpar caches no servidor
3. ✅ Testar requisições
4. ⏳ Monitorar logs por 24-48h
5. ⏳ Remover middlewares antigos após confirmação de estabilidade

## Notas Técnicas

- O middleware usa injeção de dependências do Laravel
- Todos os erros são logados e relançados
- Tempos de execução são medidos e logados
- Compatível com a arquitetura DDD existente


