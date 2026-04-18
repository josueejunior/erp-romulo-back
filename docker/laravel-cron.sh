#!/bin/bash

# Script wrapper para executar comandos Laravel dentro do cron
# Garante que o ambiente PHP e variáveis de ambiente estejam corretamente configurados

set -e

# Diretório da aplicação
cd /var/www/html || exit 1

# Carregar variáveis de ambiente do .env se existir
if [ -f .env ]; then
    set -a
    source .env
    set +a
fi

# Executar o comando Laravel passado como argumentos
/usr/local/bin/php artisan "$@"

