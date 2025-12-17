# 🔴 Redis no Docker - Guia Rápido

## ✅ Redis já está configurado no docker-compose.yml!

O Redis já foi adicionado ao `docker-compose.yml` e está pronto para uso.

## 🚀 Como usar

### 1. Configurar .env

Adicione estas variáveis no seu `.env`:

```env
REDIS_CLIENT=predis
REDIS_HOST=redis          # ← Nome do serviço no docker-compose.yml
REDIS_PORT=6379
REDIS_PASSWORD=           # ← Deixe vazio se não usar senha
REDIS_DB=0
REDIS_CACHE_DB=1
CACHE_STORE=redis         # ← Usar Redis como cache padrão
```

### 2. Iniciar containers

```bash
cd erp-romulo-back
docker-compose up -d
```

Isso iniciará:
- ✅ PostgreSQL
- ✅ Redis
- ✅ Laravel App

### 3. Verificar se Redis está funcionando

```bash
# Ver logs do Redis
docker-compose logs -f redis

# Testar conexão
docker-compose exec redis redis-cli ping
# Deve retornar: PONG

# Ver estatísticas
docker-compose exec redis redis-cli INFO stats
```

### 4. Verificar se a aplicação está usando Redis

```bash
# Ver logs da aplicação
docker-compose logs -f app

# Testar cache via Artisan
docker-compose exec app php artisan tinker
# No tinker:
Cache::put('test', 'valor', 60);
Cache::get('test');
# Deve retornar: "valor"
```

## 🔧 Comandos Úteis

### Limpar cache do Redis
```bash
# Limpar todo o cache
docker-compose exec redis redis-cli FLUSHALL

# Limpar cache de um tenant específico
docker-compose exec app php artisan redis:clear --tenant=tenant-id
```

### Ver chaves no Redis
```bash
# Listar todas as chaves (cuidado em produção!)
docker-compose exec redis redis-cli KEYS "*"

# Contar chaves
docker-compose exec redis redis-cli DBSIZE
```

### Monitorar Redis em tempo real
```bash
# Ver comandos sendo executados
docker-compose exec redis redis-cli MONITOR
```

## 📊 Verificar Estatísticas

```bash
# Estatísticas gerais
docker-compose exec redis redis-cli INFO

# Apenas estatísticas de cache
docker-compose exec redis redis-cli INFO stats

# Memória usada
docker-compose exec redis redis-cli INFO memory
```

## ⚠️ Troubleshooting

### Redis não está respondendo

```bash
# Verificar se o container está rodando
docker-compose ps redis

# Ver logs
docker-compose logs redis

# Reiniciar Redis
docker-compose restart redis
```

### Aplicação não consegue conectar ao Redis

1. Verifique se `REDIS_HOST=redis` no `.env` (não `localhost`)
2. Verifique se o container Redis está rodando: `docker-compose ps`
3. Verifique os logs: `docker-compose logs app`

### Cache não está funcionando

1. Verifique se `CACHE_STORE=redis` no `.env`
2. Limpe o cache: `docker-compose exec app php artisan cache:clear`
3. Verifique se Redis está acessível: `docker-compose exec redis redis-cli ping`

## 🎯 Próximos Passos

Após iniciar os containers, o Redis estará automaticamente:
- ✅ Cacheando dados do dashboard
- ✅ Cacheando listagens de processos
- ✅ Cacheando cálculos de saldo
- ✅ Cacheando relatórios financeiros
- ✅ Cacheando eventos do calendário

Tudo funcionando automaticamente! 🚀
