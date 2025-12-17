# 🔧 Fix: Erro ao adicionar Redis no Docker

## ❌ Erro Encontrado

```
KeyError: 'ContainerConfig'
```

Este erro geralmente ocorre quando há um container antigo com configuração incompatível.

## ✅ Solução

### Opção 1: Remover e Recriar (Recomendado)

```bash
# Parar todos os containers
docker-compose down

# Remover o container problemático especificamente
docker rm -f erp-licitacoes-app

# Reconstruir a imagem (se necessário)
docker-compose build app

# Iniciar novamente
docker-compose up -d
```

### Opção 2: Limpar tudo e recomeçar

```bash
# Parar e remover tudo (incluindo volumes - CUIDADO!)
docker-compose down -v

# Reconstruir tudo
docker-compose build --no-cache

# Iniciar
docker-compose up -d
```

### Opção 3: Remover apenas o container problemático

```bash
# Parar o container
docker stop erp-licitacoes-app

# Remover o container
docker rm erp-licitacoes-app

# Iniciar novamente
docker-compose up -d
```

## 🔍 Verificar Status

Após executar a solução, verifique:

```bash
# Ver containers rodando
docker-compose ps

# Ver logs
docker-compose logs -f

# Verificar Redis especificamente
docker-compose exec redis redis-cli ping
# Deve retornar: PONG
```

## 📝 Nota

O erro `ContainerConfig` geralmente acontece quando:
- Há um container antigo com configuração incompatível
- A imagem foi atualizada mas o container antigo ainda existe
- Há conflito de volumes ou configurações

A solução mais simples é remover o container antigo e recriar.
