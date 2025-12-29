# 📘 Seeders - Guia de Uso

## 🎯 Traits Disponíveis

### 1. `HasTimestampsCustomizados`

Fornece métodos auxiliares para trabalhar com timestamps em português (`criado_em`, `atualizado_em`).

**Métodos:**
- `withTimestamps(array $data): array` - Adiciona timestamps aos dados
- `createWithTimestamps(string $model, array $data)` - Cria registro com timestamps
- `updateOrCreateWithTimestamps(string $model, array $conditions, array $data)` - Atualiza ou cria com timestamps

**Exemplo:**
```php
use Database\Seeders\Traits\HasTimestampsCustomizados;

class MeuSeeder extends Seeder
{
    use HasTimestampsCustomizados;

    public function run(): void
    {
        $this->createWithTimestamps(AdminUser::class, [
            'name' => 'Admin',
            'email' => 'admin@exemplo.com',
            'password' => Hash::make('senha'),
        ]);
    }
}
```

### 2. `HasTenantContext`

Fornece métodos auxiliares para trabalhar no contexto de tenants.

**Métodos:**
- `withTenant(Tenant $tenant, callable $callback)` - Executa callback no contexto de um tenant
- `forEachTenant(callable $callback)` - Executa callback para cada tenant

**Exemplo:**
```php
use Database\Seeders\Traits\HasTenantContext;

class MeuSeeder extends Seeder
{
    use HasTenantContext;

    public function run(): void
    {
        $tenant = Tenant::first();
        
        $this->withTenant($tenant, function() {
            // Código executado no contexto do tenant
            Processo::create([...]);
        });
    }
}
```

### 3. `HasUserCreation`

Fornece métodos auxiliares para criar usuários com roles e associar a empresas.

**Métodos:**
- `createOrUpdateUser(array $userData, ?string $role = null)` - Cria ou atualiza usuário com role
- `associateUserToEmpresa(User $user, $empresa, string $perfil = 'consulta')` - Associa usuário a empresa

**Exemplo:**
```php
use Database\Seeders\Traits\HasUserCreation;

class MeuSeeder extends Seeder
{
    use HasUserCreation;

    public function run(): void
    {
        $user = $this->createOrUpdateUser([
            'name' => 'João',
            'email' => 'joao@exemplo.com',
            'password' => 'senha123',
        ], 'Administrador');

        $empresa = Empresa::first();
        $this->associateUserToEmpresa($user, $empresa, 'admin');
    }
}
```

## 📋 Seeders Disponíveis

### `AdminUserSeeder`
Cria usuário administrador do sistema central.

**Uso:**
```bash
php artisan db:seed --class=AdminUserSeeder
```

### `DatabaseSeeder`
Seeder principal que cria:
- Admin user
- Tenant de exemplo
- Empresa
- Usuários com roles
- Órgão e setor

**Uso:**
```bash
php artisan db:seed
```

### `RolesPermissionsSeeder`
Cria roles e permissões do sistema.

**Uso:**
```bash
php artisan db:seed --class=RolesPermissionsSeeder
```

### `PlanosSeeder`
Cria planos de assinatura (Básico, Profissional, Enterprise).

**Uso:**
```bash
php artisan db:seed --class=PlanosSeeder
```

### `DashboardDemoSeeder`
Cria dados de demonstração para o dashboard (processos, documentos).

**Uso:**
```bash
php artisan db:seed --class=DashboardDemoSeeder
```

## 🔧 Boas Práticas

1. **Sempre use traits** quando disponíveis para padronizar código
2. **Use timestamps customizados** com `HasTimestampsCustomizados`
3. **Trabalhe no contexto do tenant** usando `HasTenantContext`
4. **Crie usuários** usando `HasUserCreation` para garantir consistência
5. **Verifique se já existe** antes de criar para evitar duplicatas

## ⚠️ Importante

- Seeders que criam dados no tenant devem usar `HasTenantContext`
- Seeders que criam dados no banco central não precisam de contexto de tenant
- Sempre use `withTimestamps()` para modelos que usam timestamps customizados




