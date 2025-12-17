# 🐳 Docker Setup - ERP Licitações

Este documento explica como usar o Docker para configurar e executar o sistema ERP Licitações com PostgreSQL, migrations e seeds automáticos.

## 📋 Pré-requisitos

- Docker
- Docker Compose

## 🚀 Início Rápido

### 1. Configurar Variáveis de Ambiente

Crie um arquivo `.env` na raiz do projeto (ou copie de `.env.example`):

```bash
cp .env.example .env
```

Configure as variáveis de banco de dados (opcional, já tem valores padrão):

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=erp_licitacoes
DB_USERNAME=erp_user
DB_PASSWORD=erp123

# Para executar seeds automaticamente (padrão: true)
RUN_SEEDS=true

# Porta da aplicação (padrão: 8001)
APP_PORT=8001
```

### 2. Construir e Iniciar os Containers

```bash
# Construir as imagens
docker-compose build

# Iniciar os containers (PostgreSQL + Redis + Laravel)
docker-compose up -d

# Ver os logs
docker-compose logs -f
```

### 3. Acessar a Aplicação

A aplicação estará disponível em: **http://localhost:8001**

## 🔧 O que acontece automaticamente?

Quando você inicia os containers, o script `docker-entrypoint.sh` executa automaticamente:

1. ✅ **Aguarda PostgreSQL estar pronto** - O script aguarda o banco estar disponível
2. ✅ **Aguarda Redis estar pronto** - O script aguarda o Redis estar disponível
3. ✅ **Limpa cache** - Remove cache do Laravel
4. ✅ **Gera APP_KEY** - Se não existir, gera automaticamente
5. ✅ **Executa migrations do banco central** - Cria tabelas de tenants
6. ✅ **Executa migrations dos tenants** - Cria tabelas dos tenants existentes
7. ✅ **Executa seeds** - Cria dados iniciais (tenant, usuários, órgãos, etc.)

## 📊 Dados Iniciais Criados

Após executar os seeds, você terá:

### Tenant (Empresa)
- **ID**: `empresa-exemplo`
- **Razão Social**: Empresa Exemplo LTDA
- **CNPJ**: 12.345.678/0001-90

### Usuários
- **admin@exemplo.com** (Administrador) - Senha: `password`
- **operacional@exemplo.com** (Operacional) - Senha: `password`
- **financeiro@exemplo.com** (Financeiro) - Senha: `password`
- **consulta@exemplo.com** (Consulta) - Senha: `password`

### Órgão
- **UASG**: 123456
- **Razão Social**: Órgão Público Exemplo
- **Setor**: Setor de Compras

## 🛠️ Comandos Úteis

### Ver logs
```bash
# Todos os serviços
docker-compose logs -f

# Apenas aplicação
docker-compose logs -f app

# Apenas PostgreSQL
docker-compose logs -f postgres
```

### Executar comandos Artisan
```bash
# Dentro do container
docker-compose exec app php artisan [comando]

# Exemplos:
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan tenants:migrate
```

### Acessar PostgreSQL
```bash
# Via container
docker-compose exec postgres psql -U erp_user -d erp_licitacoes

# Ou via cliente externo
# Host: localhost
# Port: 5432
# User: erp_user
# Password: erp123
# Database: erp_licitacoes
```

### Acessar Redis
```bash
# Via container (CLI do Redis)
docker-compose exec redis redis-cli

# Com senha (se configurada)
docker-compose exec redis redis-cli -a ${REDIS_PASSWORD}

# Verificar conexão
docker-compose exec redis redis-cli ping
# Deve retornar: PONG

# Ver estatísticas
docker-compose exec redis redis-cli INFO stats
```

### Limpar cache do Redis
```bash
# Limpar todo o cache
docker-compose exec redis redis-cli FLUSHALL

# Limpar cache de um tenant específico (via Artisan)
docker-compose exec app php artisan redis:clear --tenant=tenant-id
```

### Parar containers
```bash
docker-compose down
```

### Parar e remover volumes (⚠️ apaga dados)
```bash
docker-compose down -v
```

### Reconstruir containers
```bash
docker-compose build --no-cache
docker-compose up -d
```

## 🔄 Atualizar Aplicação

### Sem perder dados
```bash
# Parar containers
docker-compose down

# Reconstruir apenas a aplicação
docker-compose build app

# Iniciar novamente
docker-compose up -d
```

### Com dados limpos (⚠️ apaga tudo)
```bash
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d
```

## 🚫 Desabilitar Seeds Automáticos

Se você não quiser que os seeds sejam executados automaticamente:

```bash
# No docker-compose.yml ou .env
RUN_SEEDS=false docker-compose up -d
```

Ou adicione no `.env`:
```env
RUN_SEEDS=false
```

## 📁 Estrutura de Volumes

- **postgres_data**: Dados persistentes do PostgreSQL
- **redis_data**: Dados persistentes do Redis (RDB + AOF)
- **./storage**: Arquivos de storage do Laravel
- **./bootstrap/cache**: Cache do Laravel

## 🔍 Troubleshooting

### PostgreSQL não está pronto
O script aguarda automaticamente o PostgreSQL estar pronto. Se houver problemas, verifique os logs:
```bash
docker-compose logs postgres
```

### Erro de permissões
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
```

### Limpar tudo e começar do zero
```bash
docker-compose down -v
docker system prune -a
docker-compose build --no-cache
docker-compose up -d
```

### Verificar se PostgreSQL está rodando
```bash
docker-compose ps
docker-compose exec postgres pg_isready -U erp_user
```

## 🌐 Usar PostgreSQL Externo

Se você já tem um PostgreSQL rodando externamente, edite o `docker-compose.yml`:

```yaml
services:
  app:
    # ... outras configurações
    environment:
      DB_HOST: 172.22.0.2  # IP do seu PostgreSQL
      DB_PORT: 5434        # Porta do seu PostgreSQL
      # ... outras variáveis
    # Remova o depends_on do postgres
```

E remova ou comente o serviço `postgres` no `docker-compose.yml`.

## ✅ Verificação

Após iniciar os containers, verifique:

1. ✅ Containers rodando: `docker-compose ps`
2. ✅ Logs sem erros: `docker-compose logs`
3. ✅ Aplicação acessível: http://localhost:8001
4. ✅ Login funcionando: Use `admin@exemplo.com` / `password`

## 📝 Notas

- O PostgreSQL usa um volume persistente, então seus dados não serão perdidos ao reiniciar
- O Redis usa um volume persistente com AOF (Append Only File) habilitado para persistência
- As migrations são executadas automaticamente a cada inicialização
- Os seeds são executados apenas se `RUN_SEEDS=true` (padrão)
- O script aguarda automaticamente o PostgreSQL e Redis estarem prontos antes de executar migrations
- O Redis está configurado para usar `predis` como cliente (não requer extensão PHP phpredis)




