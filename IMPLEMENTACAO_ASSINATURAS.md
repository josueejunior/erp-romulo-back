# 💳 Implementação do Sistema de Assinaturas

## ✅ O que foi criado

### 1. Migrations
- ✅ `2025_12_19_000001_create_planos_table.php` - Tabela de planos
- ✅ `2025_12_19_000002_create_assinaturas_table.php` - Tabela de assinaturas
- ✅ `2025_12_19_000003_add_subscription_fields_to_tenants_table.php` - Campos no tenant

### 2. Models
- ✅ `Plano.php` - Model de planos
- ✅ `Assinatura.php` - Model de assinaturas com métodos úteis
- ✅ `Tenant.php` - Atualizado com relacionamentos e métodos

### 3. Middleware
- ✅ `CheckSubscription.php` - Verifica assinatura ativa e bloqueia acesso se necessário

### 4. Seeder
- ✅ `PlanosSeeder.php` - Cria 3 planos padrão (Básico, Profissional, Enterprise)

## 🚀 Como usar

### 1. Executar migrations
```bash
php artisan migrate
```

### 2. Executar seeder de planos
```bash
php artisan db:seed --class=PlanosSeeder
```

### 3. Aplicar middleware nas rotas

No arquivo `routes/api.php`:
```php
Route::middleware(['auth:sanctum', 'tenancy', 'subscription'])->group(function () {
    // Rotas que precisam de assinatura ativa
    Route::apiResource('processos', ProcessoController::class);
    Route::apiResource('usuarios', UserController::class);
    // ...
});
```

No arquivo `bootstrap/app.php`:
```php
$middleware->alias([
    'subscription' => \App\Http\Middleware\CheckSubscription::class,
    // ...
]);
```

### 4. Criar assinatura para um tenant

```php
use App\Models\Tenant;
use App\Models\Plano;
use App\Models\Assinatura;

$tenant = Tenant::find('empresa-exemplo');
$plano = Plano::where('nome', 'Profissional')->first();

$assinatura = Assinatura::create([
    'tenant_id' => $tenant->id,
    'plano_id' => $plano->id,
    'status' => 'ativa',
    'data_inicio' => now(),
    'data_fim' => now()->addMonth(),
    'valor_pago' => $plano->preco_mensal,
    'dias_grace_period' => 7,
]);

// Atualizar tenant
$tenant->plano_atual_id = $plano->id;
$tenant->assinatura_atual_id = $assinatura->id;
$tenant->limite_processos = $plano->limite_processos;
$tenant->limite_usuarios = $plano->limite_usuarios;
$tenant->save();
```

## 🔒 Validações nos Controllers

### ProcessoController
```php
public function store(Request $request)
{
    $tenant = tenancy()->tenant;
    
    if (!$tenant->podeCriarProcesso()) {
        $plano = $tenant->planoAtual;
        $processosAtivos = Processo::where('empresa_id', $tenant->id)->count();
        
        return response()->json([
            'message' => "Você atingiu o limite de processos do seu plano ({$plano->limite_processos} processos). Faça upgrade para criar mais processos.",
            'code' => 'PROCESS_LIMIT_REACHED',
            'limite' => $plano->limite_processos,
            'utilizado' => $processosAtivos,
        ], 403);
    }
    
    // Continuar criação...
}
```

### UserController
```php
public function store(Request $request)
{
    $tenant = tenancy()->tenant;
    
    if (!$tenant->podeAdicionarUsuario()) {
        $plano = $tenant->planoAtual;
        $usuarios = User::whereHas('empresas', function($q) use ($tenant) {
            $q->where('empresas.id', $tenant->id);
        })->count();
        
        return response()->json([
            'message' => "Você atingiu o limite de usuários do seu plano ({$plano->limite_usuarios} usuários). Faça upgrade para adicionar mais usuários.",
            'code' => 'USER_LIMIT_REACHED',
            'limite' => $plano->limite_usuarios,
            'utilizado' => $usuarios,
        ], 403);
    }
    
    // Continuar criação...
}
```

## 📊 Próximos Passos

1. **Criar Controllers de API**
   - `PlanoController` - Listar planos
   - `AssinaturaController` - Gerenciar assinaturas

2. **Criar Interface no Frontend**
   - Tela de planos
   - Dashboard de assinatura
   - Notificações de vencimento

3. **Integração com Gateway de Pagamento** (futuro)
   - Stripe, PagSeguro, etc.
   - Webhooks de confirmação
   - Renovação automática

## ⚠️ Importante

- O middleware verifica assinatura em TODAS as rotas protegidas
- Grace period de 7 dias (configurável)
- Limites são verificados antes de criar recursos
- Mensagens de erro são amigáveis e informativas
