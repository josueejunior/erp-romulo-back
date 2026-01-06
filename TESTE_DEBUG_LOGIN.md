# 🔥 TESTE DE DEBUG - LOGIN TRAVANDO

## Alterações feitas para diagnóstico:

### 1. HandleApiErrors com dd()
- Adicionado `dd('HANDLE API ERRORS CHEGOU AQUI')` no início do método `handle()`
- Se não parar aqui → middleware não está no pipeline

### 2. Throttle removido temporariamente
- Comentado `->middleware(['throttle:20,1', 'throttle:50,60'])` na rota `/auth/login`
- Se funcionar → problema é cache/redis

### 3. Comandos para rodar no servidor:

```bash
# Limpar todos os caches
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Verificar rotas
php artisan route:list --path=api/v1/auth/login

# Testar login
curl -X POST https://api.addsimp.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Origin: https://gestor.addsimp.com" \
  -d '{"email":"test@test.com","password":"test123"}'
```

## Resultados esperados:

### Se aparecer o dd():
✅ HandleApiErrors está no pipeline
➡️ Problema está DEPOIS (controller/DI/FormRequest)

### Se NÃO aparecer o dd():
❌ HandleApiErrors NÃO está no pipeline
➡️ Problema de configuração do middleware

### Se funcionar sem throttle:
✅ Problema é cache/redis
➡️ Verificar CACHE_DRIVER e Redis

### Se continuar travando:
➡️ Problema está no controller ou DI

## Próximos testes (se necessário):

### 4. Simplificar controller
```php
class AuthController extends Controller
{
    public function login(Request $request)
    {
        return response()->json(['ok' => true, 'test' => 'controller_reached']);
    }
}
```

### 5. Remover FormRequest
Trocar `login(LoginRequest $request)` por `login(Request $request)`

### 6. Verificar .env
```
CACHE_DRIVER=file
RATE_LIMITER=cache
```

