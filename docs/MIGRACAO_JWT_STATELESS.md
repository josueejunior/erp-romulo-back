# Migração para JWT Stateless

## 🎯 Objetivo

Migrar de **Sanctum (stateful)** para **JWT Stateless** para:
- ✅ Eliminar dependência de sessão/Redis
- ✅ Escalabilidade horizontal fácil
- ✅ Funciona igual em SPA, mobile, parceiros
- ✅ Sem CSRF, sem cookies, sem estado
- ✅ Resolver problemas de travamento

## 📦 Dependências

### 1. Instalar firebase/php-jwt

```bash
composer require firebase/php-jwt
```

## ⚙️ Configuração

### 1. Arquivo de Configuração

Criado: `config/jwt.php`

**Variáveis de ambiente (.env)**:
```env
JWT_SECRET=base64:your-secret-key-here  # Use APP_KEY como fallback
JWT_ISSUER=https://api.addsimp.com     # URL da API
JWT_EXPIRATION=3600                     # 1 hora em segundos
```

### 2. Gerar Secret JWT

```bash
# Usar APP_KEY existente ou gerar novo
php artisan key:generate

# Ou definir manualmente no .env
JWT_SECRET=base64:$(openssl rand -base64 32)
```

## 🔄 Mudanças Implementadas

### 1. Serviço JWT (`app/Services/JWTService.php`)

**Responsabilidades**:
- Gerar tokens JWT com payload customizado
- Validar tokens JWT
- Gerenciar expiração e assinatura

**Estrutura do Token**:
```json
{
  "iss": "api.addsimp.com",
  "sub": "user_id",
  "tenant_id": "uuid",
  "empresa_id": 1,
  "role": "admin",
  "is_admin": true,
  "iat": 1700000000,
  "exp": 1700003600,
  "nbf": 1700000000
}
```

### 2. Middleware JWT (`app/Http/Middleware/AuthenticateJWT.php`)

**Funcionalidades**:
- Valida token do header `Authorization: Bearer <token>`
- Injeta payload no request
- Define usuário autenticado no guard (compatibilidade)

### 3. Middleware Unificado (`app/Http/Middleware/AuthenticateAndBootstrap.php`)

**Atualizado para**:
- Usar JWT em vez de Sanctum
- Validar token JWT
- Extrair dados do payload (user_id, tenant_id, empresa_id)
- Inicializar tenancy baseado no payload
- Fazer bootstrap do ApplicationContext

### 4. Use Cases Atualizados

**LoginUseCase**:
- Gera token JWT com `user_id`, `tenant_id`, `empresa_id`
- Retorna token JWT em vez de Sanctum token

**RegisterUseCase**:
- Gera token JWT após registro
- Retorna token JWT em vez de Sanctum token

**AuthController**:
- Admin login gera JWT com `is_admin: true`
- Usuário comum gera JWT com tenant/empresa

**AdminAuthController**:
- Gera JWT para admin em vez de Sanctum token

## 🔐 Fluxo de Autenticação

### Login
```
1. POST /api/v1/auth/login
   ↓
2. Valida credenciais
   ↓
3. Gera JWT com payload:
   {
     user_id: 1,
     tenant_id: "uuid",
     empresa_id: 1,
     role: "admin" (opcional),
     is_admin: false
   }
   ↓
4. Retorna token JWT
```

### Requisições Autenticadas
```
1. Request com header: Authorization: Bearer <jwt_token>
   ↓
2. AuthenticateAndBootstrap valida JWT
   ↓
3. Extrai payload (user_id, tenant_id, empresa_id)
   ↓
4. Inicializa tenancy baseado em tenant_id
   ↓
5. Faz bootstrap do ApplicationContext
   ↓
6. Continua com a requisição
```

## 📝 Exemplo de Uso

### Frontend (JavaScript)

```javascript
// Login
const response = await fetch('/api/v1/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email, password })
});

const { token } = await response.json();

// Salvar token
localStorage.setItem('token', token);

// Requisições autenticadas
const data = await fetch('/api/v1/payments/processar-assinatura', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'X-Empresa-ID': '1',
    'X-Tenant-ID': '1'
  },
  body: JSON.stringify({ ... })
});
```

## 🚀 Deploy

### 1. Instalar Dependência

```bash
composer require firebase/php-jwt
```

### 2. Configurar Variáveis

```bash
# Adicionar ao .env
JWT_SECRET=base64:your-secret-key
JWT_ISSUER=https://api.addsimp.com
JWT_EXPIRATION=3600
```

### 3. Limpar Caches

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### 4. Testar

```bash
# Login
curl -X POST https://api.addsimp.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'

# Usar token retornado
curl -X POST https://api.addsimp.com/api/v1/payments/processar-assinatura \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "X-Empresa-ID: 1" \
  -H "X-Tenant-ID: 1" \
  -d '{...}'
```

## 🔄 Compatibilidade

### Mantido para Transição

- Sanctum ainda está instalado (não removido)
- Código legado que usa `auth('sanctum')` continua funcionando
- Tokens Sanctum antigos ainda funcionam (se necessário)

### Migração Gradual

1. ✅ Backend migrado para JWT
2. ⏳ Frontend precisa atualizar para usar JWT
3. ⏳ Deprecar tokens Sanctum antigos (opcional)

## ⚠️ Notas Importantes

1. **Segurança**: Use `JWT_SECRET` forte e único
2. **Expiração**: Tokens expiram automaticamente (padrão: 1 hora)
3. **Refresh Token**: Não implementado - usuário precisa fazer login novamente após expiração
4. **Revogação**: Tokens não podem ser revogados individualmente (stateless)
   - Solução: Implementar blacklist em Redis (opcional) ou reduzir tempo de expiração

## 🎉 Benefícios

✅ **Sem Estado**: Não precisa de sessão ou Redis
✅ **Escalável**: Funciona em múltiplos servidores sem compartilhar estado
✅ **Simples**: Token contém tudo necessário (user_id, tenant_id, empresa_id)
✅ **Rápido**: Validação é apenas verificação de assinatura
✅ **Universal**: Funciona em SPA, mobile, APIs de parceiros

## 📚 Referências

- [JWT.io](https://jwt.io/) - Documentação JWT
- [firebase/php-jwt](https://github.com/firebase/php-jwt) - Biblioteca PHP JWT

