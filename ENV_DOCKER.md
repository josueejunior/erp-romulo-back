# 📝 Arquivo .env para Docker

Este documento explica como configurar o arquivo `.env` para usar com Docker.

## 🚀 Configuração Rápida

### 1. Criar arquivo .env

```bash
cd erp-romulo-back
cp .env.example .env
```

### 2. Configurações Essenciais para Docker

As configurações mais importantes para o Docker são:

```env
# Banco de dados - IMPORTANTE: usar 'postgres' como host
DB_CONNECTION=pgsql
DB_HOST=postgres          # ← Nome do serviço no docker-compose.yml
DB_PORT=5432              # ← Porta interna do container
DB_DATABASE=erp_licitacoes
DB_USERNAME=erp_user
DB_PASSWORD=erp123

# Docker específico
RUN_SEEDS=true            # Executar seeds automaticamente
APP_PORT=8001             # Porta no host (acessível externamente)
```

## 📋 Explicação das Variáveis

### Banco de Dados

| Variável | Valor Docker | Explicação |
|----------|--------------|------------|
| `DB_HOST` | `postgres` | **IMPORTANTE**: Use o nome do serviço do docker-compose.yml, não `localhost` ou `127.0.0.1` |
| `DB_PORT` | `5432` | Porta interna do container PostgreSQL |
| `DB_DATABASE` | `erp_licitacoes` | Nome do banco de dados |
| `DB_USERNAME` | `erp_user` | Usuário do PostgreSQL |
| `DB_PASSWORD` | `erp123` | Senha do PostgreSQL |

### Docker Específico

| Variável | Valor | Explicação |
|----------|-------|------------|
| `RUN_SEEDS` | `true` ou `false` | Se `true`, executa seeds automaticamente ao iniciar |
| `APP_PORT` | `8001` | Porta no host onde a aplicação será acessível |

### Aplicação

| Variável | Valor Recomendado | Explicação |
|----------|-------------------|------------|
| `APP_NAME` | `"ERP Licitações"` | Nome da aplicação |
| `APP_ENV` | `local` | Ambiente (local, production, etc) |
| `APP_DEBUG` | `true` | Modo debug (true para desenvolvimento) |
| `APP_URL` | `http://localhost:8001` | URL da aplicação (deve corresponder à porta) |
| `APP_KEY` | (gerado automaticamente) | Chave de criptografia (gerada pelo script) |

## 🔧 Exemplo Completo Mínimo

Para começar rapidamente, use este `.env` mínimo:

```env
APP_NAME="ERP Licitações"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8001

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=erp_licitacoes
DB_USERNAME=erp_user
DB_PASSWORD=erp123

RUN_SEEDS=true
APP_PORT=8001

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,127.0.0.1:8001
```

## ⚠️ Importante

### DB_HOST no Docker

**ERRADO:**
```env
DB_HOST=127.0.0.1    # ❌ Não funciona no Docker
DB_HOST=localhost    # ❌ Não funciona no Docker
```

**CORRETO:**
```env
DB_HOST=postgres      # ✅ Nome do serviço no docker-compose.yml
```

### Portas

- **`DB_PORT=5432`**: Porta interna do container (não mude)
- **`APP_PORT=8001`**: Porta no host (pode mudar se necessário)

### APP_KEY

O `APP_KEY` será gerado automaticamente pelo script `docker-entrypoint.sh` se não existir. Você pode deixar vazio:

```env
APP_KEY=
```

## 🔄 Usar PostgreSQL Externo

Se você já tem um PostgreSQL rodando externamente (fora do Docker):

```env
# Use o IP/host do seu PostgreSQL externo
DB_HOST=172.22.0.2    # IP do seu PostgreSQL
DB_PORT=5434          # Porta do seu PostgreSQL
DB_DATABASE=erp_licitacoes
DB_USERNAME=erp_user
DB_PASSWORD=erp123
```

E remova o serviço `postgres` do `docker-compose.yml`.

## ✅ Verificação

Após criar o `.env`, verifique:

1. ✅ `DB_HOST=postgres` (não `localhost`)
2. ✅ `APP_URL` corresponde à porta `APP_PORT`
3. ✅ `RUN_SEEDS=true` se quiser dados iniciais
4. ✅ `APP_KEY` pode estar vazio (será gerado)

## 🚀 Próximos Passos

1. Crie o `.env`:
   ```bash
   cp .env.example .env
   ```

2. Ajuste as variáveis se necessário

3. Inicie os containers:
   ```bash
   docker-compose up -d --build
   ```

4. A aplicação estará em: http://localhost:8001




