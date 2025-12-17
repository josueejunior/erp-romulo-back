# 🔧 Limpar Cache do Laravel

Se você está recebendo erro "Rota não encontrada" após adicionar novas rotas, limpe o cache:

## No servidor (Docker):

```bash
# Entrar no container
docker exec -it erp-licitacoes-app bash

# Limpar cache de rotas
php artisan route:clear

# Limpar cache de configuração
php artisan config:clear

# Limpar cache geral
php artisan cache:clear

# Verificar rotas registradas
php artisan route:list | grep contratos
```

## Ou executar tudo de uma vez:

```bash
docker exec -it erp-licitacoes-app php artisan route:clear && \
docker exec -it erp-licitacoes-app php artisan config:clear && \
docker exec -it erp-licitacoes-app php artisan cache:clear
```

## Verificar se a rota está registrada:

```bash
docker exec -it erp-licitacoes-app php artisan route:list --path=api/v1/contratos
```

## Se ainda não funcionar:

1. Verifique se o método `listarTodos` existe no `ContratoController`
2. Verifique se a rota está dentro do middleware de autenticação
3. Verifique se você está autenticado (a rota requer `auth:sanctum`)




