# API REST - ERP Licitações

## Configuração Inicial

### 1. Instalar Dependências
```bash
cd back-end
composer install
```

### 2. Configurar Banco de Dados PostgreSQL

Edite o arquivo `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=erp_licitacoes_central
DB_USERNAME=postgres
DB_PASSWORD=sua_senha
```

### 3. Executar Migrations

```bash
# Migrations do banco central (tenants, users, etc)
php artisan migrate

# As migrations dos tenants serão executadas automaticamente quando um tenant for criado
```

### 4. Publicar Configurações do Tenancy

```bash
php artisan vendor:publish --tag=tenancy-config
php artisan vendor:publish --tag=tenancy-migrations
```

### 5. Publicar Configurações do Sanctum

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 6. Criar Primeiro Tenant

```bash
php artisan tinker
```

```php
$tenant = \App\Models\Tenant::create([
    'id' => \Illuminate\Support\Str::uuid(),
    'razao_social' => 'Empresa Exemplo LTDA',
    'cnpj' => '12.345.678/0001-90',
    'email' => 'contato@exemplo.com',
    'status' => 'ativa',
]);

// O banco será criado automaticamente
```

### 7. Criar Primeiro Usuário no Tenant

```php
tenancy()->initialize($tenant);

$user = \App\Models\User::create([
    'name' => 'Administrador',
    'email' => 'admin@exemplo.com',
    'password' => \Illuminate\Support\Facades\Hash::make('password'),
]);

$user->assignRole('Administrador');
```

## Estrutura de Pastas

```
back-end/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/          # Controllers de API
│   │   ├── Resources/        # API Resources
│   │   └── Middleware/       # Middlewares (Tenancy, etc)
│   └── Models/               # Modelos Eloquent
├── database/
│   └── migrations/
│       └── tenant/           # Migrations que serão executadas em cada tenant
├── routes/
│   └── api.php               # Rotas da API
└── config/
    ├── tenancy.php           # Configuração do Tenancy
    ├── sanctum.php           # Configuração do Sanctum
    └── cors.php              # Configuração do CORS
```

## Fluxo de Autenticação

1. **Login**: Cliente envia `email`, `password` e `tenant_id`
2. **Resposta**: API retorna `token` e informações do `tenant`
3. **Próximas Requisições**: Cliente envia:
   - Header: `Authorization: Bearer {token}`
   - Header: `X-Tenant-ID: {tenant_id}`

## Exemplo de Uso com React

```javascript
// services/api.js
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
});

// Adicionar token e tenant_id automaticamente
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  const tenantId = localStorage.getItem('tenant_id');
  
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  
  if (tenantId) {
    config.headers['X-Tenant-ID'] = tenantId;
  }
  
  return config;
});

// Interceptor para tratar erros
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expirado ou inválido
      localStorage.removeItem('token');
      localStorage.removeItem('tenant_id');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
```

## Testando a API

### Com cURL

```bash
# Criar tenant
curl -X POST http://localhost:8000/api/tenants \
  -H "Content-Type: application/json" \
  -d '{
    "razao_social": "Empresa Teste",
    "cnpj": "12.345.678/0001-90"
  }'

# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@exemplo.com",
    "password": "password",
    "tenant_id": "uuid-do-tenant"
  }'

# Listar processos
curl -X GET http://localhost:8000/api/processos \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-ID: {tenant_id}"
```

## Próximos Passos

1. Configurar frontend React
2. Implementar tratamento de erros
3. Adicionar validações adicionais
4. Implementar rate limiting
5. Adicionar testes automatizados






