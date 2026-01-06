# 🔄 Migração JWT - Pendências

## ✅ Já Migrado

1. ✅ `LoginUseCase` - Gera JWT
2. ✅ `RegisterUseCase` - Gera JWT
3. ✅ `AuthController::login` - Gera JWT para admin
4. ✅ `AdminAuthController::login` - Gera JWT
5. ✅ `AuthenticateAndBootstrap` - Valida JWT
6. ✅ Rotas principais - Usam `AuthenticateAndBootstrap`

## ✅ Migração Completa

Todas as partes críticas foram migradas para JWT:

### 1. ✅ LogoutUseCase
**Status**: Migrado
**Mudança**: Removida tentativa de deletar token Sanctum. JWT é stateless.

### 2. ✅ GetUserUseCase
**Status**: Migrado
**Mudança**: Usa payload JWT do request em vez de `currentAccessToken()`

### 3. ✅ Rotas Admin
**Status**: Migrado
**Mudança**: Usa `AuthenticateAndBootstrap` em vez de `auth:sanctum`

### 4. ✅ AdminAuthController::logout
**Status**: Migrado
**Mudança**: Removida tentativa de deletar token Sanctum

### 5. ✅ AuthIdentityService
**Status**: Migrado
**Mudança**: Usa payload JWT do request em vez de `currentAccessToken()`

### 6. ⚠️ InitializeApplicationContext
**Status**: Não migrado (não é mais usado)
**Nota**: Middleware legado que não está sendo usado nas rotas. Pode ser atualizado no futuro se necessário.

## 📋 Checklist Final

- [x] Instalar firebase/php-jwt
- [x] Criar JWTService
- [x] Criar AuthenticateJWT middleware
- [x] Atualizar AuthenticateAndBootstrap para usar JWT
- [x] Migrar LoginUseCase para JWT
- [x] Migrar RegisterUseCase para JWT
- [x] Migrar AuthController::login para JWT
- [x] Migrar AdminAuthController::login para JWT
- [x] Migrar LogoutUseCase (remover delete token)
- [x] Migrar GetUserUseCase (usar payload JWT)
- [x] Migrar AuthIdentityService (usar payload JWT)
- [x] Migrar rotas admin para AuthenticateAndBootstrap
- [x] Criar config/jwt.php
- [x] Registrar middleware no bootstrap/app.php

