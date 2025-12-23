# 🚀 Como Executar Migrations

Este sistema usa **multi-tenancy** (Stancl Tenancy), então há dois tipos de migrations:

1. **Migrations do Banco Central** - Tabelas compartilhadas (tenants, domains, planos, etc.)
2. **Migrations dos Tenants** - Tabelas específicas de cada tenant (processos, contratos, etc.)

---

## 📋 Pré-requisitos

1. Configure o arquivo `.env` com as credenciais do banco de dados
2. Certifique-se de que o banco de dados central existe
3. Tenha acesso ao terminal/command line

---

## 🔧 1. Migrations do Banco Central

As migrations do banco central criam as tabelas compartilhadas entre todos os tenants.

### Executar todas as migrations do central

```bash
cd erp-romulo-back
php artisan migrate
```

### Executar migrations de um módulo específico

```bash
# Exemplo: apenas migrations do módulo Processo
php artisan migrate --path=database/migrations/Modules/Processo
```

### Ver status das migrations

```bash
php artisan migrate:status
```

### Rollback (desfazer última migration)

```bash
php artisan migrate:rollback
```

### Rollback de todas as migrations

```bash
php artisan migrate:rollback --step=999
```

### Refresh (rollback + migrate novamente)

```bash
# ⚠️ ATENÇÃO: Isso apaga todos os dados!
php artisan migrate:refresh
```

---

## 🏢 2. Migrations dos Tenants

As migrations dos tenants são executadas em cada banco de dados de tenant individualmente.

### Executar migrations em TODOS os tenants

```bash
php artisan tenants:migrate
```

### Executar migrations em tenants específicos

```bash
# Por ID
php artisan tenants:migrate --tenants=tenant-id-1,tenant-id-2

# Por domínio
php artisan tenants:migrate --tenants=dominio1.com,dominio2.com
```

### Executar migrations com path específico

```bash
php artisan tenants:migrate --path=database/migrations/tenant
```

### Ver status das migrations dos tenants

```bash
php artisan tenants:migrate --tenants=tenant-id --pretend
```

### Rollback dos tenants

```bash
# Rollback em todos os tenants
php artisan tenants:migrate-rollback

# Rollback em tenants específicos
php artisan tenants:migrate-rollback --tenants=tenant-id-1,tenant-id-2
```

### Refresh dos tenants (rollback + migrate)

```bash
# ⚠️ ATENÇÃO: Isso apaga todos os dados dos tenants!
php artisan tenants:migrate-refresh

# Com seeds após refresh
php artisan tenants:migrate-refresh --seed

# Em tenants específicos
php artisan tenants:migrate-refresh --tenants=tenant-id-1 --seed
```

---

## 📁 Estrutura das Migrations

```
database/migrations/
├── Modules/              # Migrations do banco central (organizadas por módulo)
│   ├── Auth/
│   ├── Processo/
│   ├── Orcamento/
│   └── ...
├── System/               # Migrations do sistema base
│   ├── Cache/
│   ├── Jobs/
│   └── ...
├── Tenancy/              # Migrations de multi-tenancy (banco central)
│   ├── tenants
│   └── domains
└── tenant/               # Migrations dos tenants (executadas em cada tenant)
    ├── processos
    ├── contratos
    └── ...
```

---

## 🔄 Fluxo Completo de Setup

### 1. Primeira vez (setup inicial)

```bash
# 1. Executar migrations do banco central
php artisan migrate

# 2. Criar um tenant (se ainda não existir)
php artisan tinker
# No tinker:
# $tenant = \App\Models\Tenant::create(['id' => 'meu-tenant', 'razao_social' => 'Minha Empresa', 'cnpj' => '12.345.678/0001-90']);

# 3. Executar migrations nos tenants
php artisan tenants:migrate
```

### 2. Após criar nova migration

```bash
# 1. Criar a migration
php artisan make:migration create_nova_tabela --path=database/migrations/Modules/Processo

# 2. Editar a migration
# (editar o arquivo criado)

# 3. Executar no banco central
php artisan migrate

# 4. Executar nos tenants (se for migration de tenant)
php artisan tenants:migrate
```

---

## 🛠️ Comandos Úteis

### Forçar execução (sem confirmação)

```bash
php artisan migrate --force
php artisan tenants:migrate --force
```

### Executar apenas uma migration específica

```bash
php artisan migrate --path=database/migrations/Modules/Processo/2025_01_01_000001_create_processos.php
```

### Ver quais migrations serão executadas (dry-run)

```bash
php artisan migrate --pretend
php artisan tenants:migrate --pretend
```

### Limpar cache de migrations

```bash
php artisan config:clear
php artisan cache:clear
```

---

## ⚠️ Avisos Importantes

1. **Backup antes de refresh**: `migrate:refresh` e `tenants:migrate-refresh` **apagam todos os dados**!
2. **Ambiente de produção**: Use `--force` apenas em produção ou scripts automatizados
3. **Ordem de execução**: Sempre execute migrations do central antes dos tenants
4. **Teste primeiro**: Teste migrations em ambiente de desenvolvimento antes de produção

---

## 🐛 Troubleshooting

### Erro: "Migration table not found"

```bash
# Recriar tabela de migrations
php artisan migrate:install
```

### Erro: "Tenant database does not exist"

```bash
# Criar banco do tenant manualmente ou via comando
php artisan tenants:create-database tenant-id
```

### Erro: "Class not found"

```bash
# Limpar cache e recompilar
php artisan config:clear
php artisan cache:clear
composer dump-autoload
```

### Ver logs de erro

```bash
# Ver logs do Laravel
tail -f storage/logs/laravel.log
```

---

## 📚 Referências

- [Documentação Laravel Migrations](https://laravel.com/docs/migrations)
- [Documentação Stancl Tenancy](https://tenancyforlaravel.com/docs/v3/)
- Guia interno: `database/migrations/GUIA_MIGRATIONS.md`
- Estrutura: `database/migrations/README.md`

