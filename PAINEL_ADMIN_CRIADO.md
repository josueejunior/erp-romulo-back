# 🎛️ Painel Admin Central - Criado

## O que foi implementado

Painel administrativo central para gerenciar todas as empresas (tenants) do sistema, com login e senha próprios.

## Estrutura Backend

### 1. **Model AdminUser**
- `app/Models/AdminUser.php` - Model para administradores centrais
- Tabela: `admin_users`
- Usa Sanctum para autenticação

### 2. **Controllers Admin**
- `app/Http/Controllers/Admin/AdminAuthController.php` - Login/logout admin
- `app/Http/Controllers/Admin/AdminTenantController.php` - CRUD de empresas

### 3. **Middleware**
- `app/Http/Middleware/IsSuperAdmin.php` - Protege rotas admin

### 4. **Rotas API**
- `/api/admin/login` - Login admin
- `/api/admin/logout` - Logout admin
- `/api/admin/me` - Dados do admin autenticado
- `/api/admin/empresas` - Listar empresas (GET)
- `/api/admin/empresas` - Criar empresa (POST)
- `/api/admin/empresas/{id}` - Ver/Editar empresa (GET/PUT)
- `/api/admin/empresas/{id}` - Inativar empresa (DELETE)
- `/api/admin/empresas/{id}/reativar` - Reativar empresa (POST)

### 5. **Migration e Seeder**
- `database/migrations/2025_01_22_000001_create_admin_users_table.php`
- `database/seeders/AdminUserSeeder.php` - Cria admin padrão

## Estrutura Frontend

### 1. **Páginas Admin**
- `src/pages/admin/AdminLogin.jsx` - Login do admin
- `src/pages/admin/AdminDashboard.jsx` - Dashboard com estatísticas
- `src/pages/admin/AdminEmpresas.jsx` - Lista de empresas
- `src/pages/admin/AdminEmpresaForm.jsx` - Criar/Editar empresa

### 2. **Serviços**
- `src/services/adminAuthService.js` - Autenticação admin
- `src/services/adminApi.js` - API client para admin (sem tenant_id)

### 3. **Componentes**
- `src/components/ProtectedAdminRoute.jsx` - Proteção de rotas admin

### 4. **Rotas Frontend**
- `/admin/login` - Login
- `/admin/dashboard` - Dashboard
- `/admin/empresas` - Lista empresas
- `/admin/empresas/novo` - Nova empresa
- `/admin/empresas/:id/editar` - Editar empresa

## Como usar

### 1. **Executar Migration e Seeder**
```bash
php artisan migrate
php artisan db:seed --class=AdminUserSeeder
```

### 2. **Credenciais Padrão**
- **Email:** `admin@sistema.com`
- **Senha:** `admin123`

### 3. **Acessar Painel Admin**
1. Acesse `/admin/login` no frontend
2. Faça login com as credenciais acima
3. Você será redirecionado para `/admin/dashboard`

## Funcionalidades

### Dashboard
- Estatísticas de empresas (total, ativas, inativas)
- Acesso rápido para gerenciar empresas

### Gerenciamento de Empresas
- ✅ Listar todas as empresas
- ✅ Buscar por razão social, CNPJ ou email
- ✅ Filtrar por status (ativa/inativa)
- ✅ Criar nova empresa
- ✅ Editar empresa existente
- ✅ Inativar empresa (não exclui, apenas marca como inativa)
- ✅ Reativar empresa inativada
- ✅ Paginação de resultados

### Segurança
- ✅ Autenticação separada do sistema de tenants
- ✅ Middleware protege todas as rotas admin
- ✅ Token Sanctum para autenticação
- ✅ Logout limpa tokens e redireciona

## Diferenças do Sistema Normal

| Aspecto | Sistema Normal | Painel Admin |
|---------|---------------|--------------|
| Autenticação | Dentro do tenant | Fora do tenant (central) |
| Usuário | User (dentro do tenant) | AdminUser (central) |
| Token | `token` | `admin_token` |
| Rotas | `/api/v1/*` | `/api/admin/*` |
| Tenant ID | Obrigatório | Não usado |

## Próximos Passos (Opcional)

1. **Adicionar mais funcionalidades:**
   - Gerenciar usuários de cada empresa
   - Ver estatísticas detalhadas de cada empresa
   - Gerenciar assinaturas
   - Ver logs de atividades

2. **Melhorias de segurança:**
   - 2FA (autenticação de dois fatores)
   - Histórico de ações do admin
   - Permissões granulares

3. **Melhorias de UX:**
   - Dashboard com gráficos
   - Exportação de relatórios
   - Notificações

## Arquivos Criados/Modificados

### Backend
- ✅ `app/Models/AdminUser.php`
- ✅ `app/Http/Controllers/Admin/AdminAuthController.php`
- ✅ `app/Http/Controllers/Admin/AdminTenantController.php`
- ✅ `app/Http/Middleware/IsSuperAdmin.php`
- ✅ `database/migrations/2025_01_22_000001_create_admin_users_table.php`
- ✅ `database/seeders/AdminUserSeeder.php`
- ✅ `routes/api.php` (adicionadas rotas admin)

### Frontend
- ✅ `src/pages/admin/AdminLogin.jsx`
- ✅ `src/pages/admin/AdminDashboard.jsx`
- ✅ `src/pages/admin/AdminEmpresas.jsx`
- ✅ `src/pages/admin/AdminEmpresaForm.jsx`
- ✅ `src/services/adminAuthService.js`
- ✅ `src/services/adminApi.js`
- ✅ `src/components/ProtectedAdminRoute.jsx`
- ✅ `src/App.jsx` (adicionadas rotas admin)

## Teste

1. Execute as migrations e seeders
2. Acesse `/admin/login`
3. Faça login com `admin@sistema.com` / `admin123`
4. Teste criar, editar e inativar empresas
