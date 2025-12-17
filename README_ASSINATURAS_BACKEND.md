# 💳 Sistema de Assinaturas - Backend

## ✅ Implementação Completa

### Migrations
- ✅ `create_planos_table.php`
- ✅ `create_assinaturas_table.php`
- ✅ `add_subscription_fields_to_tenants_table.php`

### Models
- ✅ `Plano.php` - Model com métodos úteis
- ✅ `Assinatura.php` - Model com renovação/cancelamento
- ✅ `Tenant.php` - Atualizado com relacionamentos

### Controllers
- ✅ `PlanoController.php` - Listar planos
- ✅ `AssinaturaController.php` - CRUD completo

### Middleware
- ✅ `CheckSubscription.php` - Bloqueia acesso se inválida

### Seeder
- ✅ `PlanosSeeder.php` - 3 planos padrão

### Rotas
- ✅ `/api/v1/planos` - GET (público)
- ✅ `/api/v1/planos/{id}` - GET (público)
- ✅ `/api/v1/assinaturas` - GET, POST (autenticado)
- ✅ `/api/v1/assinaturas/atual` - GET (autenticado)
- ✅ `/api/v1/assinaturas/status` - GET (autenticado)
- ✅ `/api/v1/assinaturas/{id}/renovar` - POST (autenticado)
- ✅ `/api/v1/assinaturas/{id}/cancelar` - POST (autenticado)

## 🚀 Como Usar

### 1. Executar Migrations
```bash
php artisan migrate
```

### 2. Executar Seeder
```bash
php artisan db:seed --class=PlanosSeeder
```

### 3. Aplicar Middleware (Opcional)
```php
// Em routes/api.php
Route::middleware(['auth:sanctum', 'tenancy', 'subscription'])->group(function () {
    // Rotas que precisam de assinatura ativa
});
```

## 📋 Endpoints

### GET /api/v1/planos
Lista todos os planos disponíveis

### GET /api/v1/assinaturas/atual
Retorna assinatura atual do tenant

### GET /api/v1/assinaturas/status
Retorna status com limites utilizados

### POST /api/v1/assinaturas
Cria nova assinatura
```json
{
  "plano_id": 1,
  "periodo": "mensal" // ou "anual"
}
```

### POST /api/v1/assinaturas/{id}/renovar
Renova assinatura
```json
{
  "meses": 1 // ou 12
}
```

## ⚠️ Importante

- Planos e Assinaturas estão no **banco central** (não tenant)
- Middleware verifica assinatura automaticamente
- Limites são verificados nos controllers
- Grace period de 7 dias configurável
