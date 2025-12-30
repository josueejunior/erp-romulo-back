# 🔧 Correções Aplicadas para Produção

## Problemas Identificados nos Logs

1. ❌ `include(/var/www/html/vendor/composer/../../app/Http/Controllers/Api/FixUserRolesController.php): Failed to open stream`
2. ❌ `include(/var/www/html/vendor/composer/../../app/Models/Contrato.php): Failed to open stream`
3. ⚠️ `Class "MercadoPago\SDK" not found` (dependência já está no composer.json)

## ✅ Correções Aplicadas

### 1. Routes (routes/api.php)
- ✅ Atualizado `FixUserRolesController` de `App\Http\Controllers\Api\FixUserRolesController` para `App\Modules\Auth\Controllers\FixUserRolesController`

### 2. AppServiceProvider
- ✅ Atualizado Policy de `App\Models\Contrato` para `App\Modules\Contrato\Models\Contrato`

### 3. ContratoRepositoryInterface
- ✅ Atualizado tipo de retorno de `App\Models\Contrato` para `App\Modules\Contrato\Models\Contrato`

### 4. ValidarVinculoProcesso Rule
- ✅ Atualizado `App\Models\Contrato` para `App\Modules\Contrato\Models\Contrato`
- ✅ Atualizado `App\Models\Empenho` para `App\Modules\Empenho\Models\Empenho`
- ✅ Atualizado `App\Models\AutorizacaoFornecimento` para `App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento`

### 5. AuditObserver
- ✅ Atualizado todas as referências de modelos:
  - `App\Models\Processo` → `App\Modules\Processo\Models\Processo`
  - `App\Models\Contrato` → `App\Modules\Contrato\Models\Contrato`
  - `App\Models\Orcamento` → `App\Modules\Orcamento\Models\Orcamento`
  - `App\Models\NotaFiscal` → `App\Modules\NotaFiscal\Models\NotaFiscal`
  - `App\Models\Empenho` → `App\Modules\Empenho\Models\Empenho`
  - `App\Models\AutorizacaoFornecimento` → `App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento`

## 📋 Ações Necessárias no Servidor

Após fazer `git pull`, execute:

```bash
# 1. Limpar cache do Laravel
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 2. Regenerar autoloader do Composer
composer dump-autoload

# 3. (Opcional) Otimizar para produção
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## ⚠️ Sobre o MercadoPago\SDK

O erro `Class "MercadoPago\SDK" not found` indica que a dependência não está instalada. A dependência `mercadopago/dx-php` já está no `composer.json`, então execute:

```bash
composer install --no-dev --optimize-autoloader
```

ou se já tiver instalado:

```bash
composer update mercadopago/dx-php --no-dev
```

## ✅ Status

Todas as referências antigas foram corrigidas. Após executar os comandos acima no servidor, os erros devem ser resolvidos.

