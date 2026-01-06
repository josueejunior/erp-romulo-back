# ✅ Checklist de Deploy - Migração JWT

## 📦 1. Instalar Dependência

```bash
composer require firebase/php-jwt
```

## ⚙️ 2. Configurar Variáveis de Ambiente

Adicionar ao `.env`:

```env
# JWT Configuration
JWT_SECRET=base64:your-secret-key-here  # Use APP_KEY como fallback se não definir
JWT_ISSUER=https://api.addsimp.com      # URL da sua API
JWT_EXPIRATION=3600                     # 1 hora em segundos (padrão)
```

**Gerar JWT_SECRET** (opcional, se quiser diferente do APP_KEY):
```bash
# Opção 1: Usar APP_KEY existente (recomendado)
# Não precisa definir JWT_SECRET, será usado APP_KEY automaticamente

# Opção 2: Gerar novo secret
php artisan key:generate
# Copiar o valor gerado para JWT_SECRET
```

## 🧹 3. Limpar Caches

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan optimize:clear
```

## ✅ 4. Verificar Arquivos Migrados

Confirmar que os seguintes arquivos foram atualizados:

- ✅ `composer.json` - firebase/php-jwt adicionado
- ✅ `config/jwt.php` - Criado
- ✅ `app/Services/JWTService.php` - Criado
- ✅ `app/Http/Middleware/AuthenticateJWT.php` - Criado
- ✅ `app/Http/Middleware/AuthenticateAndBootstrap.php` - Atualizado para JWT
- ✅ `app/Application/Auth/UseCases/LoginUseCase.php` - Gera JWT
- ✅ `app/Application/Auth/UseCases/RegisterUseCase.php` - Gera JWT
- ✅ `app/Application/Auth/UseCases/LogoutUseCase.php` - Removido delete token
- ✅ `app/Application/Auth/UseCases/GetUserUseCase.php` - Usa payload JWT
- ✅ `app/Modules/Auth/Controllers/AuthController.php` - Gera JWT
- ✅ `app/Http/Controllers/Admin/AdminAuthController.php` - Gera JWT + logout atualizado
- ✅ `app/Services/AuthIdentityService.php` - Usa payload JWT
- ✅ `routes/api.php` - Usa AuthenticateAndBootstrap (JWT)
- ✅ `bootstrap/app.php` - Registrado alias jwt.auth

## 🧪 5. Testar

### Teste 1: Login
```bash
curl -X POST https://api.addsimp.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'
```

**Esperado**: Retorna `token` (JWT) no formato:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {...},
  "tenant": {...}
}
```

### Teste 2: Requisição Autenticada
```bash
curl -X POST https://api.addsimp.com/api/v1/payments/processar-assinatura \
  -H "Authorization: Bearer <token_jwt>" \
  -H "Content-Type: application/json" \
  -H "X-Empresa-ID: 1" \
  -H "X-Tenant-ID: 1" \
  -d '{...}'
```

**Esperado**: Requisição completa normalmente sem travamento

### Teste 3: Token Expirado
```bash
# Usar token antigo/expirado
curl -X GET https://api.addsimp.com/api/v1/auth/user \
  -H "Authorization: Bearer <token_expirado>"
```

**Esperado**: Retorna 401 com mensagem "Token inválido ou expirado"

## 🔍 6. Verificar Logs

Após deploy, verificar logs:

```
[INFO] AuthenticateAndBootstrap::handle - ✅ INÍCIO
[DEBUG] AuthenticateAndBootstrap::handle - Validando token JWT
[DEBUG] AuthenticateAndBootstrap::handle - Token JWT válido
[INFO] AuthenticateAndBootstrap::handle - Bootstrap concluído
[INFO] AuthenticateAndBootstrap::handle - ✅ FIM
```

## ⚠️ 7. Problemas Comuns

### Token não está sendo aceito
- Verificar se `JWT_SECRET` está configurado corretamente
- Verificar se o token está sendo enviado no header `Authorization: Bearer <token>`
- Verificar logs para erros de validação

### Token expira muito rápido
- Ajustar `JWT_EXPIRATION` no `.env` (em segundos)
- Padrão: 3600 (1 hora)

### Erro "Token inválido"
- Verificar se `JWT_SECRET` é o mesmo usado para gerar o token
- Verificar se o token não foi modificado
- Verificar se o token não expirou

## 🎯 8. Próximos Passos (Opcional)

### Implementar Refresh Token (Opcional)
Se quiser renovar tokens sem fazer login novamente:
- Criar endpoint `/api/v1/auth/refresh`
- Gerar novo JWT com mesmo payload mas nova expiração
- Frontend renova token automaticamente antes de expirar

### Implementar Blacklist (Opcional)
Se quiser revogar tokens individualmente:
- Usar Redis para blacklist
- Adicionar `jti` (JWT ID) no payload
- Verificar blacklist no middleware AuthenticateJWT

## ✅ Status Final

- [x] Backend 100% migrado para JWT
- [ ] Frontend precisa atualizar (opcional - JWT funciona igual Sanctum no header)
- [ ] Tokens Sanctum antigos podem ser deprecados (opcional)

