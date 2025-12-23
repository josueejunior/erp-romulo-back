# 🔧 Solução: Erro "Too Many Attempts"

## Problema

O erro `ThrottleRequestsException: Too Many Attempts` ocorre quando muitas requisições são feitas em um curto período de tempo, excedendo o limite de rate limiting.

## Correções Aplicadas

### 1. **Aumentado Limite de Rate Limiting**
- ✅ Alterado de `throttle:60,1` (60 requisições/minuto) para `throttle:120,1` (120 requisições/minuto)
- ✅ Reduz a chance de bloqueio durante desenvolvimento/testes

### 2. **Adicionado Métodos para Limpar Rate Limit**
- ✅ `RedisService::clearRateLimit($identifier)` - Limpa rate limit específico
- ✅ `RedisService::clearAllRateLimits()` - Limpa todos os rate limits (customizados e Laravel padrão)
- ✅ Comando Artisan `rate-limit:clear` para limpar via terminal com opção `--force`

### 3. **Melhorado Tratamento de Erros de Rate Limiting**
- ✅ Tratamento específico de `ThrottleRequestsException` no `HandleApiErrors`
- ✅ Mensagens mais amigáveis em português
- ✅ Headers úteis incluídos na resposta (Retry-After, X-RateLimit-*)
- ✅ Logs estruturados para monitoramento

## Soluções Imediatas

### Opção 1: Limpar Rate Limit via Comando (Recomendado)
```bash
# Limpar todos os rate limits (com confirmação)
php artisan rate-limit:clear

# Limpar todos os rate limits (sem confirmação - útil para scripts)
php artisan rate-limit:clear --force

# Ou limpar um específico (se souber o identificador)
php artisan rate-limit:clear "rate_limit:IP:GET:/api/v1/orgaos"
```

**Nota:** O comando agora limpa tanto os rate limits customizados (Redis) quanto os do Laravel padrão (cache).

### Opção 2: Limpar Redis Diretamente
```bash
# Acessar Redis CLI
redis-cli

# Limpar todas as chaves de rate limit
KEYS rate_limit:*
DEL rate_limit:*

# Ou limpar tudo (CUIDADO!)
FLUSHALL
```

### Opção 3: Aguardar o Rate Limit Expirar
- O rate limit expira automaticamente após 1 minuto
- Aguarde 60 segundos e tente novamente

## Verificar Rate Limit Atual

```bash
# Acessar Redis CLI
redis-cli

# Ver todas as chaves de rate limit
KEYS rate_limit:*

# Ver valor de uma chave específica
GET "rate_limit:IP:GET:/api/v1/orgaos"
```

## Prevenção

### 1. Verificar se há Loops no Frontend
- Abra o DevTools (F12) → Network
- Verifique se há requisições sendo feitas em loop
- Se houver, corrija o código do frontend

### 2. Reduzir Logs Excessivos
- Os logs adicionados no `OrgaoController` podem estar causando muitas requisições
- Considere remover ou reduzir a frequência dos logs em produção

### 3. Ajustar Rate Limiting por Rota
Se necessário, você pode ter rate limits diferentes por rota:
```php
// Em routes/api.php
Route::middleware(['auth:sanctum', 'tenancy', 'throttle:200,1'])->group(function () {
    // Rotas que precisam de mais requisições
});

Route::middleware(['auth:sanctum', 'tenancy', 'throttle:60,1'])->group(function () {
    // Rotas que precisam de menos requisições
});
```

## Comandos Úteis

```bash
# Limpar rate limit
php artisan rate-limit:clear

# Limpar cache geral
php artisan cache:clear

# Ver logs em tempo real
tail -f storage/logs/laravel.log

# Verificar se Redis está funcionando
php artisan tinker
>>> \App\Services\RedisService::isAvailable()
```

## Resultado

Após limpar o rate limit:
- ✅ Você poderá fazer requisições novamente
- ✅ O limite foi aumentado para 120 requisições/minuto
- ✅ Você pode limpar o rate limit quando necessário
