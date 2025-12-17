# 🚀 Comandos para Sistema de Assinaturas

## 1. Executar Migrations

```bash
php artisan migrate
```

Isso criará as tabelas:
- `planos`
- `assinaturas`
- Adiciona campos em `tenants`

## 2. Executar Seeder de Planos

```bash
php artisan db:seed --class=PlanosSeeder
```

Isso criará 3 planos:
- Básico (R$ 99/mês)
- Profissional (R$ 299/mês)
- Enterprise (R$ 799/mês)

## 3. Verificar Rotas

```bash
php artisan route:list --path=planos
php artisan route:list --path=assinaturas
```

## 4. Testar API

### Listar Planos (público)
```bash
curl https://api.addireta.com/api/v1/planos
```

### Obter Assinatura Atual (autenticado)
```bash
curl -H "Authorization: Bearer TOKEN" \
     -H "X-Tenant-ID: empresa-exemplo" \
     https://api.addireta.com/api/v1/assinaturas/atual
```

## ⚠️ Problemas Comuns

### Rota não encontrada
- Verificar se migrations foram executadas
- Verificar se rotas estão no arquivo `routes/api.php`
- Limpar cache: `php artisan route:clear`

### 404 em /assinaturas/atual
- Verificar se tenant tem `assinatura_atual_id`
- Verificar se existe assinatura para o tenant
- Verificar se middleware `tenancy` está funcionando

### Planos não aparecem
- Executar seeder: `php artisan db:seed --class=PlanosSeeder`
- Verificar se planos estão ativos na tabela `planos`
