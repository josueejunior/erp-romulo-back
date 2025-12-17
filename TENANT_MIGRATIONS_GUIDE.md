# Guia de Migrations e Seeds para Tenants

## 📋 Comandos Disponíveis

### 1. Executar Migrations para TODOS os Tenants

```bash
php artisan tenants:migrate
```

Este comando executa todas as migrations pendentes em todos os tenants cadastrados.

**Com opções:**
```bash
# Forçar execução (útil em produção)
php artisan tenants:migrate --force

# Executar apenas para um tenant específico
php artisan tenants:migrate --tenants=empresa-exemplo

# Executar para múltiplos tenants
php artisan tenants:migrate --tenants=empresa-exemplo,empresa-teste

# Executar apenas uma migration específica
php artisan tenants:migrate --path=database/migrations/tenant/2025_12_15_202103_make_setor_id_nullable_in_processos_table.php
```

### 2. Executar Seeds para TODOS os Tenants

```bash
php artisan tenants:seed
```

Este comando executa o seeder configurado (`DatabaseSeeder` por padrão) em todos os tenants.

**Com opções:**
```bash
# Executar apenas para um tenant específico
php artisan tenants:seed --tenants=empresa-exemplo

# Executar um seeder específico
php artisan tenants:seed --class=RolesPermissionsSeeder

# Executar com tenant específico e seeder específico
php artisan tenants:seed --tenants=empresa-exemplo --class=RolesPermissionsSeeder
```

### 3. Executar Migrations e Seeds Juntos

```bash
# Migrations primeiro
php artisan tenants:migrate --force

# Depois os seeds
php artisan tenants:seed
```

### 4. Executar para um Tenant Específico (via Tinker)

```bash
php artisan tinker
```

```php
// Inicializar o tenant
$tenant = \App\Models\Tenant::find('empresa-exemplo');
tenancy()->initialize($tenant);

// Executar migrations
\Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);

// Executar seeds
\Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

// Finalizar contexto do tenant
tenancy()->end();
```

## 🔧 Configuração

As migrations de tenant estão localizadas em:
```
database/migrations/tenant/
```

O seeder padrão está configurado em `config/tenancy.php`:
```php
'seeder_parameters' => [
    '--class' => 'DatabaseSeeder',
],
```

## 📝 Exemplos Práticos

### Exemplo 1: Aplicar nova migration em todos os tenants

```bash
# Criar a migration (se ainda não criou)
php artisan make:migration nome_da_migration --path=database/migrations/tenant

# Executar para todos os tenants
php artisan tenants:migrate --force
```

### Exemplo 2: Aplicar migration apenas em um tenant de teste

```bash
php artisan tenants:migrate --tenants=empresa-teste --force
```

### Exemplo 3: Rodar seeds apenas para um tenant específico

```bash
php artisan tenants:seed --tenants=empresa-exemplo
```

### Exemplo 4: Verificar status das migrations

```bash
# Para um tenant específico via tinker
php artisan tinker
```

```php
$tenant = \App\Models\Tenant::find('empresa-exemplo');
tenancy()->initialize($tenant);
\Artisan::call('migrate:status');
tenancy()->end();
```

## ⚠️ Importante

1. **Sempre use `--force` em produção** para evitar confirmações interativas
2. **Backup antes de executar** migrations em produção
3. **Teste primeiro em um tenant de desenvolvimento**
4. As migrations de tenant são executadas **dentro do contexto de cada tenant**, então cada tenant tem seu próprio banco de dados

## 🐳 No Docker

Se estiver usando Docker, os comandos são executados dentro do container:

```bash
# Entrar no container
docker exec -it erp-licitacoes-app bash

# Executar migrations
php artisan tenants:migrate --force

# Executar seeds
php artisan tenants:seed
```

Ou execute diretamente:

```bash
docker exec -it erp-licitacoes-app php artisan tenants:migrate --force
docker exec -it erp-licitacoes-app php artisan tenants:seed
```

## 🔍 Troubleshooting

### Erro: "Tenant not found"
- Verifique se o tenant existe: `php artisan tinker` → `\App\Models\Tenant::all()`
- Verifique o ID do tenant usado no comando

### Erro: "Database does not exist"
- O banco do tenant precisa ser criado primeiro
- Use o `DatabaseSeeder` que cria o tenant e o banco automaticamente

### Migrations não estão sendo encontradas
- Verifique se as migrations estão em `database/migrations/tenant/`
- Verifique a configuração em `config/tenancy.php` → `migration_parameters`




