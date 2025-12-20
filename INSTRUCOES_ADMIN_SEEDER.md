# 🔧 Instruções: Criar Admin User

## Problema
O `AdminUserSeeder` não está sendo executado automaticamente porque precisa ser chamado no `DatabaseSeeder`.

## Solução Aplicada
✅ Adicionado `$this->call(AdminUserSeeder::class);` no início do `DatabaseSeeder`

## Como Executar

### 1. Executar Migration (se ainda não executou)
```bash
php artisan migrate
```

Isso criará a tabela `admin_users`.

### 2. Executar Seeder
```bash
php artisan db:seed
```

OU executar apenas o AdminUserSeeder:
```bash
php artisan db:seed --class=AdminUserSeeder
```

## Credenciais Padrão Criadas

Após executar o seeder, você terá:

- **Email:** `admin@sistema.com`
- **Senha:** `admin123`

## Verificar se Funcionou

Execute no banco central (não no tenant):
```sql
SELECT * FROM admin_users;
```

Deve retornar 1 registro com:
- email: `admin@sistema.com`
- name: `Administrador`

## Teste de Login

1. Acesse `/admin/login` no frontend
2. Use as credenciais:
   - Email: `admin@sistema.com`
   - Senha: `admin123`

## Nota Importante

O `AdminUserSeeder` é executado **ANTES** de qualquer inicialização de tenant, pois a tabela `admin_users` está no banco **central**, não no banco do tenant.
