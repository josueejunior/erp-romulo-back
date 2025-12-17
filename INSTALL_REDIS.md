# 🔴 Instalação do Redis no Servidor

## ⚠️ Erro Encontrado

Se você está recebendo o erro:
```
Class "Predis\Client" not found
```

Isso significa que o pacote `predis/predis` não foi instalado no container.

## ✅ Solução

### Opção 1: Instalar via Composer no Container (Recomendado)

```bash
# Entrar no container
docker-compose exec app bash

# Instalar predis
composer require predis/predis

# Sair do container
exit
```

### Opção 2: Reconstruir o Container

```bash
# Parar containers
docker-compose down

# Remover container antigo
docker rm -f erp-licitacoes-app

# Reconstruir
docker-compose build --no-cache app

# Iniciar
docker-compose up -d
```

### Opção 3: Verificar se o composer.json está atualizado

O `composer.json` já tem `predis/predis` adicionado. Se o container foi criado antes dessa atualização, você precisa:

```bash
# Entrar no container
docker-compose exec app bash

# Atualizar dependências
composer update predis/predis

# Ou reinstalar tudo
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
```

## 🔍 Verificar Instalação

```bash
# Verificar se predis está instalado
docker-compose exec app composer show predis/predis

# Testar conexão com Redis
docker-compose exec app php artisan tinker
# No tinker:
use Illuminate\Support\Facades\Redis;
Redis::ping();
# Deve retornar: "PONG"
```

## 📝 Nota sobre o Erro de Tabela

O erro `relation "nota_fiscals" does not exist` foi corrigido adicionando `protected $table = 'notas_fiscais';` no modelo `NotaFiscal.php`.

Se ainda ocorrer, execute as migrations:

```bash
docker-compose exec app php artisan tenants:migrate --force
```

