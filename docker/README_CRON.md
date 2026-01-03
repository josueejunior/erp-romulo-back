# Configuração de Cron Jobs no Docker

Este diretório contém os arquivos necessários para executar cron jobs dentro do container Docker.

## Arquivos

- `crontab` - Arquivo de configuração do cron com os jobs agendados
- `laravel-cron.sh` - Script wrapper que garante o ambiente correto para executar comandos Laravel

## Cron Jobs Configurados

1. **Verificar pagamentos pendentes** - A cada 2 horas
   - Comando: `pagamentos:verificar-pendentes --horas=1`
   - Verifica pagamentos pendentes no Mercado Pago e atualiza assinaturas

2. **Verificar assinaturas expiradas** - Diariamente às 2h
   - Comando: `assinaturas:verificar-expiradas --bloquear`
   - Verifica e processa assinaturas expiradas

3. **Verificar documentos vencendo** - Diariamente às 6h
   - Comando: `documentos:vencimento`
   - Verifica documentos de habilitação vencendo/vencidos

4. **Cleanup de documentos** - Diariamente às 3h30
   - Comando: `documentos:cleanup-processos`
   - Remove uploads de documentos não referenciados

## Logs

Os logs dos cron jobs são salvos em `/var/log/cron.log` dentro do container.

Para ver os logs:
```bash
docker exec erp-licitacoes-app tail -f /var/log/cron.log
```

## Como Funciona

1. O `Dockerfile` instala o `cron` e copia os arquivos de configuração
2. O `docker-entrypoint.sh` inicia o serviço `cron` antes de iniciar o servidor Laravel
3. O `laravel-cron.sh` é um wrapper que:
   - Navega para o diretório da aplicação
   - Carrega variáveis de ambiente do `.env`
   - Executa o comando Laravel com o PHP correto

## Testar Manualmente

Para testar um cron job manualmente:
```bash
docker exec erp-licitacoes-app /usr/local/bin/laravel-cron.sh pagamentos:verificar-pendentes --horas=1
```

## Adicionar Novos Cron Jobs

1. Edite o arquivo `docker/crontab`
2. Adicione a linha no formato: `minuto hora dia mês dia-semana usuário comando`
3. Rebuild do Docker: `docker-compose build`
4. Reinicie o container: `docker-compose restart app`

## Notas Importantes

- Todos os comandos devem usar o script wrapper `/usr/local/bin/laravel-cron.sh`
- O usuário `www-data` é usado para executar os comandos
- Os logs são salvos em `/var/log/cron.log`
- O cron roda em background enquanto o container está ativo

