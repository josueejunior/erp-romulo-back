# 🔴 Configuração e Uso do Redis

## 📋 Instalação

### 1. Instalar dependência PHP
```bash
composer require predis/predis
```

### 2. Configurar variáveis de ambiente (.env)
```env
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1

# Opcional: Configurar cache padrão para Redis
CACHE_STORE=redis
```

### 3. Instalar Redis no servidor

#### Docker:
```bash
docker run -d --name redis -p 6379:6379 redis:alpine
```

#### Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install redis-server
sudo systemctl start redis
sudo systemctl enable redis
```

#### Windows (via WSL ou Docker):
```bash
# Via Docker (recomendado)
docker run -d --name redis -p 6379:6379 redis:alpine
```

## 🚀 Casos de Uso Implementados

### 1. Cache de Dashboard
```php
use App\Services\RedisService;

// Cache automático no DashboardController
// Cache por 5 minutos
RedisService::cacheDashboard($tenantId, $data, 300);
$cached = RedisService::getDashboard($tenantId);
```

### 2. Cache de Processos
```php
// Cache de listagens de processos com filtros
RedisService::cacheProcessos($tenantId, $filters, $data, 180);
$cached = RedisService::getProcessos($tenantId, $filters);
```

### 3. Cache de Saldo Financeiro
```php
// Cache de cálculos de saldo por processo
RedisService::cacheSaldo($tenantId, $processoId, $data, 600);
$cached = RedisService::getSaldo($tenantId, $processoId);
```

### 4. Cache de Relatórios Financeiros
```php
// Cache de relatórios mensais
RedisService::cacheRelatorioFinanceiro($tenantId, $mes, $ano, $data, 3600);
$cached = RedisService::getRelatorioFinanceiro($tenantId, $mes, $ano);
```

### 5. Cache de Calendário
```php
// Cache de eventos do calendário
RedisService::cacheCalendario($tenantId, $mes, $ano, $data, 1800);
$cached = RedisService::getCalendario($tenantId, $mes, $ano);
```

### 6. Rate Limiting
```php
// Limitar requisições por IP/endpoint
$identifier = "api:{$ip}:{$endpoint}";
if (!RedisService::rateLimit($identifier, 60, 60)) {
    return response()->json(['message' => 'Muitas requisições'], 429);
}
```

### 7. Lock Distribuído
```php
// Prevenir execução simultânea de operações críticas
$lockKey = "processo:{$processoId}:calcular_saldo";
if (RedisService::lock($lockKey, 10)) {
    try {
        // Operação crítica
    } finally {
        RedisService::unlock($lockKey);
    }
}
```

### 8. Cache de Sessão de Tenant
```php
// Cache de tenant ativo por usuário
RedisService::cacheTenantSession($userId, $tenantId, 3600);
$tenantId = RedisService::getTenantSession($userId);
```

## 🧹 Limpeza de Cache

### Limpar cache específico
```php
// Limpar cache de um processo
RedisService::clearSaldo($tenantId, $processoId);

// Limpar cache de processos
RedisService::clearProcessos($tenantId);

// Limpar todos os caches de um tenant
RedisService::clearAllTenantCache($tenantId);
```

### Limpar via Artisan
```bash
php artisan cache:clear
php artisan config:clear
```

## 📊 Monitoramento

### Verificar se Redis está disponível
```php
if (RedisService::isAvailable()) {
    // Usar Redis
} else {
    // Fallback para database/file cache
}
```

### Obter estatísticas
```php
$stats = RedisService::getStats();
// Retorna: connected_clients, used_memory_human, etc.
```

## 🔧 Integração Automática

Os seguintes controllers já estão integrados com Redis:

- ✅ `DashboardController` - Cache de dados do dashboard
- ✅ `ProcessoController` - Cache de listagens
- ✅ `SaldoController` - Cache de cálculos de saldo
- ✅ `RelatorioFinanceiroController` - Cache de relatórios mensais
- ✅ `CalendarioController` - Cache de eventos do calendário

### Invalidação Automática de Cache

O sistema possui um `ProcessoObserver` que invalida automaticamente os caches relacionados quando:
- Um processo é criado
- Um processo é atualizado
- Um processo é deletado

Isso garante que os dados sempre estejam atualizados no cache.

## ⚠️ Observações Importantes

1. **TTL (Time To Live)**: Cada tipo de cache tem um TTL apropriado:
   - Dashboard: 5 minutos (dados que mudam frequentemente)
   - Processos: 3 minutos (listagens)
   - Saldo: 10 minutos (cálculos pesados)
   - Relatórios: 1 hora (dados mensais)
   - Calendário: 30 minutos (eventos)

2. **Invalidação Automática**: O cache é invalidado automaticamente via `ProcessoObserver` quando:
   - Processos são criados/atualizados/deletados
   - Status de processos muda
   - Data de recebimento de pagamento é registrada

3. **Multi-tenant**: Todos os caches são isolados por `tenant_id` para garantir segurança de dados.

4. **Fallback**: Se Redis não estiver disponível, o sistema usa o cache padrão (database/file) automaticamente.

5. **Performance**: O serviço usa `SCAN` ao invés de `KEYS` para melhor performance em produção.

6. **Rate Limiting**: Middleware `RateLimitRedis` disponível para limitar requisições por IP/endpoint.

## 🐳 Docker Compose (Já Configurado)

O Redis já está configurado no `docker-compose.yml` do projeto:

```yaml
services:
  redis:
    image: redis:7-alpine
    container_name: erp-licitacoes-redis
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD:-}
    ports:
      - '${REDIS_PORT:-6379}:6379'
    volumes:
      - redis_data:/data
    networks:
      - erp-network
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5
```

### Configuração no .env para Docker

```env
REDIS_CLIENT=predis
REDIS_HOST=redis          # ← Nome do serviço no docker-compose.yml
REDIS_PORT=6379
REDIS_PASSWORD=           # ← Deixe vazio se não usar senha
REDIS_DB=0
REDIS_CACHE_DB=1
CACHE_STORE=redis
```

### Iniciar com Docker

```bash
# Iniciar todos os serviços (PostgreSQL + Redis + Laravel)
docker-compose up -d

# Ver logs do Redis
docker-compose logs -f redis

# Testar conexão com Redis
docker-compose exec redis redis-cli ping
# Deve retornar: PONG
```

## 🛠️ Comandos Artisan

### Limpar cache do Redis
```bash
# Limpar todos os caches de um tenant
php artisan redis:clear --tenant=tenant-id

# Limpar cache específico
php artisan redis:clear --tenant=tenant-id --type=dashboard
php artisan redis:clear --tenant=tenant-id --type=processos
php artisan redis:clear --tenant=tenant-id --type=relatorio
php artisan redis:clear --tenant=tenant-id --type=calendario
```

## 📝 Exemplo de Uso do Rate Limiting

Adicione nas rotas que precisam de rate limiting:

```php
// Em routes/api.php
Route::middleware(['auth:sanctum', 'tenancy', 'rate.limit.redis:60,60'])->group(function () {
    // Rotas com limite de 60 requisições por minuto
});
```

## ✅ Checklist de Implementação

- [x] Adicionar `predis/predis` ao composer.json
- [x] Configurar Redis no config/database.php
- [x] Criar RedisService com métodos úteis
- [x] Integrar cache nos controllers principais
- [x] Criar ProcessoObserver para invalidação automática
- [x] Criar middleware de rate limiting
- [x] Criar comando artisan para limpeza de cache
- [x] Documentação completa
