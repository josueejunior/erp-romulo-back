# 🔍 Debug da Rota /assinaturas/atual

## Problema
A rota `/api/v1/assinaturas/atual` está retornando 404.

## Verificações

### 1. Limpar Cache de Rotas
```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

### 2. Verificar Rotas Registradas
```bash
php artisan route:list --path=assinaturas
```

Deve mostrar:
- GET /api/v1/assinaturas/atual
- GET /api/v1/assinaturas/status
- GET /api/v1/assinaturas
- POST /api/v1/assinaturas
- etc.

### 3. Verificar Middleware
A rota está dentro do grupo:
```php
Route::middleware(['auth:sanctum', 'tenancy', 'throttle:60,1'])
```

Isso significa que precisa:
- ✅ Token de autenticação (Bearer token)
- ✅ Header X-Tenant-ID
- ✅ Rate limit (60 req/min)

### 4. Testar a Rota

#### Com cURL:
```bash
curl -X GET \
  https://api.addireta.com/api/v1/assinaturas/atual \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "X-Tenant-ID: empresa-exemplo" \
  -H "Content-Type: application/json"
```

#### Verificar Logs
```bash
tail -f storage/logs/laravel.log | grep -i "assinatura\|tenancy"
```

### 5. Possíveis Causas

#### A) Cache de Rotas
**Solução**: Limpar cache
```bash
php artisan route:clear
```

#### B) Middleware Bloqueando
**Verificar**: Se o token está válido e o tenant existe

#### C) Rota Não Registrada
**Verificar**: Se o arquivo `routes/api.php` está sendo carregado

#### D) Problema com Prefixo
**Verificar**: Se o prefixo `v1` está correto

### 6. Adicionar Rota de Teste

Se ainda não funcionar, adicionar uma rota de teste simples:

```php
Route::get('/teste-assinatura', function() {
    return response()->json([
        'message' => 'Rota funcionando',
        'tenant' => tenancy()->tenant?->id
    ]);
});
```

## Logs Adicionados

O método `atual()` agora tem logs para debug:
- Quando é chamado
- Status do tenant
- Headers da requisição

Verifique `storage/logs/laravel.log` após fazer a requisição.
