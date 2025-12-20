# 🔧 Solução para Erro no Seeder

## ❌ Erro Encontrado

```
SQLSTATE[42P01]: Undefined table: 7 ERROR:  relation "orgaos" does not exist
```

## 🔍 Causa

O seeder está tentando criar um órgão, mas as **migrations do tenant não foram executadas** ainda. A tabela `orgaos` não existe porque as migrations não rodaram.

## ✅ Solução

### Opção 1: Executar migrations primeiro (RECOMENDADO)

```bash
# Executar migrations do tenant primeiro
php artisan tenants:migrate --force

# Depois executar o seeder
php artisan db:seed
```

### Opção 2: Usar o comando que faz tudo

```bash
# Este comando executa migrations e seeds juntos
php artisan tenants:migrate --force
php artisan tenants:seed
```

### Opção 3: Seeder corrigido

O seeder foi corrigido para **verificar e executar migrations automaticamente** mesmo se o tenant já existir. Agora você pode executar:

```bash
php artisan db:seed
```

E o seeder vai garantir que as migrations estejam executadas antes de tentar criar dados.

## 📋 Passos Recomendados

1. **Executar migrations do tenant:**
   ```bash
   php artisan tenants:migrate --force
   ```

2. **Executar seeder:**
   ```bash
   php artisan db:seed
   ```

## ⚠️ Se ainda der erro

Se ainda der erro após executar as migrations, pode ser que o banco do tenant não exista. Nesse caso:

1. Verificar se o tenant existe:
   ```bash
   php artisan tinker
   ```
   ```php
   \App\Models\Tenant::all();
   ```

2. Se o tenant existir mas o banco não, você pode precisar criar manualmente ou usar:
   ```bash
   php artisan tenants:migrate --force
   ```

## ✅ Após corrigir

Após executar as migrations, o seeder deve funcionar normalmente e criar:
- ✅ Empresa
- ✅ Usuários
- ✅ Órgão
- ✅ Setor

