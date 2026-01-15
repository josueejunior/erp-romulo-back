# 🔐 Fluxo de Login - Documentação Completa

## 📋 Arquivos Envolvidos no Login

### **Backend (Laravel)**

#### 1. **Rota** - `routes/api.php`
```php
Route::post('/auth/login', [AuthController::class, 'login'])
```
- **Linha 76**: Define a rota POST `/api/auth/login`
- **Middleware**: Rate limiting (20/min, 50/hora) para prevenir brute force

#### 2. **Controller** - `app/Modules/Auth/Controllers/AuthController.php`
- **Método**: `login(LoginRequest $request)` - **Linha 96**
- **Responsabilidade**: Recebe request, valida, delega para Use Case, retorna resposta
- **Fluxo**:
  1. Valida request via `LoginRequest`
  2. Verifica se é admin (via `BuscarAdminUserPorEmailUseCase`)
  3. Se for admin → gera token JWT e retorna
  4. Se não for admin → chama `LoginUseCase`

#### 3. **Form Request** - `app/Http/Requests/Auth/LoginRequest.php`
- **Validação**: Email obrigatório, senha obrigatória, tenant_id opcional
- **Regras**: `email: required|email`, `password: required|string`

#### 4. **Use Case** - `app/Application/Auth/UseCases/LoginUseCase.php`
- **Método**: `executar(LoginDTO $dto)` - **Linha 36**
- **Lógica Principal**:
  1. **Buscar Tenant**: 
     - Se `tenant_id` fornecido → busca direto
     - Se não → usa `users_lookup` para encontrar tenant(s) do email
     - Se múltiplos tenants → lança `MultiplosTenantsException` (HTTP 300)
  2. **Inicializar Tenancy**: `tenancy()->initialize($tenant)`
  3. **Buscar Usuário**: `userRepository->buscarPorEmail($email)`
  4. **Validar Senha**: Usa Value Object `Senha` para verificar
  5. **Buscar Empresa Ativa**: Obtém empresa ativa do usuário
  6. **Validar Consistência**: Verifica se empresa e usuário estão no mesmo tenant
  7. **Gerar Token JWT**: Cria token com `user_id`, `tenant_id`, `empresa_id`
  8. **Retornar Dados**: User, Tenant, Empresa, Token

#### 5. **DTO** - `app/Application/Auth/DTOs/LoginDTO.php`
- **Estrutura**: Email, Password, TenantId (opcional)
- **Método**: `fromRequest(Request $request)` - Converte request em DTO

#### 6. **Repository** - `app/Infrastructure/Persistence/Eloquent/UserRepository.php`
- **Métodos usados**:
  - `buscarPorEmail(string $email)`: Busca usuário por email no tenant atual
  - `buscarEmpresaAtiva(int $userId)`: Busca empresa ativa do usuário
  - `buscarEmpresas(int $userId)`: Busca todas empresas do usuário

#### 7. **Service** - `app/Application/CadastroPublico/Services/UsersLookupService.php`
- **Método**: `encontrarPorEmail(string $email)`
- **Função**: Busca rápida O(1) de tenants associados ao email
- **Retorna**: Array de `UserLookup` com tenant_id e user_id

#### 8. **JWT Service** - `app/Services/JWTService.php`
- **Método**: `generateToken(array $payload)`
- **Payload**: `user_id`, `tenant_id`, `empresa_id`, `role`
- **Retorna**: Token JWT assinado

---

### **Frontend (React)**

#### 1. **Página de Login** - `erp-romulo-front/src/pages/Login.jsx`
- **Componente**: Formulário de login
- **Função**: Coleta email e senha, chama `authService.login()`

#### 2. **Service** - `erp-romulo-front/src/features/auth/services/auth.service.js`
- **Método**: `login(email, password)` - **Linha 9**
- **Fluxo**:
  1. Chama `authApi.login(email, password)`
  2. Salva token no `authStorage`
  3. Salva `tenant_id` no `sessionStorage`
  4. Salva dados do usuário
  5. Retorna dados completos

#### 3. **API Client** - `erp-romulo-front/src/features/auth/infra/auth.api.js`
- **Método**: `login(email, password)`
- **Função**: Faz requisição POST para `/api/auth/login`
- **Retorna**: `response.data` (objeto completo do backend)

#### 4. **Auth Context** - `erp-romulo-front/src/features/auth/AuthContext.jsx`
- **Função**: Gerencia estado global de autenticação
- **Métodos**: `setAuthData()`, `login()`, `logout()`, etc.

---

## 🔄 Fluxo Completo do Login

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. FRONTEND - Usuário preenche formulário                       │
│    Arquivo: Login.jsx                                           │
│    Ação: onSubmit() → authService.login(email, password)        │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. FRONTEND - Service faz requisição HTTP                       │
│    Arquivo: auth.service.js                                     │
│    Ação: authApi.login() → POST /api/auth/login                 │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. BACKEND - Rota recebe requisição                             │
│    Arquivo: routes/api.php (linha 76)                            │
│    Ação: Route::post('/auth/login', [AuthController::class, 'login']) │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. BACKEND - Form Request valida dados                          │
│    Arquivo: LoginRequest.php                                     │
│    Validação: email (required|email), password (required)       │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. BACKEND - Controller processa                                │
│    Arquivo: AuthController.php (linha 96)                       │
│    Ação:                                                         │
│    5.1. Verifica se é admin (BuscarAdminUserPorEmailUseCase)    │
│    5.2. Se admin → gera token JWT e retorna                     │
│    5.3. Se não → cria LoginDTO e chama LoginUseCase             │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. BACKEND - Use Case executa lógica de negócio                 │
│    Arquivo: LoginUseCase.php (linha 36)                         │
│    Passos:                                                       │
│    6.1. Buscar Tenant:                                          │
│         - Se tenant_id fornecido → busca direto                 │
│         - Se não → UsersLookupService.encontrarPorEmail()       │
│         - Se múltiplos → lança MultiplosTenantsException         │
│    6.2. Inicializar Tenancy: tenancy()->initialize($tenant)    │
│    6.3. Buscar Usuário: userRepository->buscarPorEmail()        │
│    6.4. Validar Senha: Senha Value Object verifica hash         │
│    6.5. Buscar Empresa Ativa: userRepository->buscarEmpresaAtiva() │
│    6.6. Validar Consistência: verifica tenant da empresa         │
│    6.7. Gerar Token JWT: JWTService->generateToken()            │
│    6.8. Retornar: user, tenant, empresa, token                   │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 7. BACKEND - Controller retorna resposta                        │
│    Arquivo: AuthController.php                                  │
│    Resposta: JSON com user, tenant, empresa, token, success      │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 8. FRONTEND - Service processa resposta                         │
│    Arquivo: auth.service.js                                     │
│    Ação:                                                         │
│    8.1. Salva token: authStorage.setToken()                      │
│    8.2. Salva tenant_id: authStorage.setTenantId()               │
│    8.3. Salva user: authStorage.setUser()                       │
│    8.4. Retorna dados completos                                 │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 9. FRONTEND - AuthContext atualiza estado                       │
│    Arquivo: AuthContext.jsx                                     │
│    Ação: setAuthData() atualiza estado global                   │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ 10. FRONTEND - Redireciona para dashboard                      │
│     Arquivo: Login.jsx                                           │
│     Ação: navigate('/') ou navigate('/admin/dashboard')         │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔑 Pontos Importantes

### **1. Busca de Tenant**
- **Prioridade 1**: `tenant_id` fornecido no request
- **Prioridade 2**: `users_lookup` table (busca O(1) por email)
- **Fallback**: Busca em todos os tenants (O(n))

### **2. Múltiplos Tenants**
- Se email está em múltiplos tenants → HTTP 300 (Multiple Choices)
- Frontend deve exibir tela de seleção
- Usuário escolhe qual tenant acessar

### **3. Validação de Senha**
- Usa Value Object `Senha` para verificar hash
- Previne timing attacks (sempre verifica mesmo se usuário não existe)
- Hash dummy usado se usuário não encontrado

### **4. Token JWT**
- **Stateless**: Não precisa de sessão no servidor
- **Payload**: `user_id`, `tenant_id`, `empresa_id`, `role`
- **Validade**: Configurada no `JWTService`

### **5. Consistência Tenant-Empresa**
- Valida se empresa ativa está no mesmo tenant do usuário
- Se não estiver, verifica permissão do usuário na empresa
- Se tiver permissão → usa tenant da empresa
- Se não tiver → falha login

---

## 📁 Estrutura de Arquivos

```
erp-romulo-back/
├── routes/
│   └── api.php                          # Rota POST /auth/login
├── app/
│   ├── Modules/Auth/Controllers/
│   │   └── AuthController.php           # Controller principal
│   ├── Http/Requests/Auth/
│   │   └── LoginRequest.php             # Validação de request
│   ├── Application/Auth/
│   │   ├── UseCases/
│   │   │   └── LoginUseCase.php         # Lógica de negócio
│   │   └── DTOs/
│   │       └── LoginDTO.php             # Data Transfer Object
│   ├── Infrastructure/Persistence/Eloquent/
│   │   └── UserRepository.php           # Acesso ao banco
│   ├── Application/CadastroPublico/Services/
│   │   └── UsersLookupService.php       # Busca rápida de tenants
│   └── Services/
│       └── JWTService.php               # Geração de tokens JWT

erp-romulo-front/
├── src/
│   ├── pages/
│   │   └── Login.jsx                    # Página de login
│   └── features/auth/
│       ├── services/
│       │   └── auth.service.js          # Service de autenticação
│       ├── infra/
│       │   └── auth.api.js              # Cliente HTTP
│       └── AuthContext.jsx              # Context de autenticação
```

---

## 🎯 Resumo Rápido

**Quando você faz login:**

1. **Frontend** envia email + senha para `/api/auth/login`
2. **Backend** valida e busca tenant via `users_lookup`
3. **Backend** inicializa tenancy e busca usuário no banco do tenant
4. **Backend** valida senha e busca empresa ativa
5. **Backend** gera token JWT com `user_id`, `tenant_id`, `empresa_id`
6. **Backend** retorna user, tenant, empresa, token
7. **Frontend** salva token e tenant_id no sessionStorage
8. **Frontend** redireciona para dashboard

**Arquivos principais:**
- **Controller**: `AuthController.php` (linha 96)
- **Use Case**: `LoginUseCase.php` (linha 36)
- **Repository**: `UserRepository.php`
- **Service**: `UsersLookupService.php`
- **Frontend**: `Login.jsx` + `auth.service.js`

