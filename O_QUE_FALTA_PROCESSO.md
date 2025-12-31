# O que falta - Processo e Documentos de Habilitacao

## Concluido agora
- Auditoria ao vincular/atualizar/download de documentos de habilitacao em processos (acao vincular_processo/atualizar_processo/download_processo com user/ip/user_agent via log). 
- Permissoes reforcadas em endpoints de documentos do processo (canManageDocuments em importar/sincronizar/atualizar/custom).
- Fluxo de vinculo de docs: importar lista, marcar status (pendente/possui/anexado), upload especifico, selecionar versao existente, doc custom.
- Listagem de documentos do processo incluindo versoes disponiveis e metadados de arquivo.
- Endpoints adicionados: atualizar documento do processo, criar doc custom, download com auditoria.
- Cleanup diario de uploads nao referenciados de documentos de processo (comando documentos:cleanup-processos agendado).

## Ainda pendente
- Opcional: notificacao/email ou webhook para documentos vencendo ou ausentes por processo.
- Rodar migracoes de tenant para novas colunas de processo_documentos e tabela de logs.
- Validar comandos/cron de alerta de vencimento rodando em producao.
- (CSV pronto) Se precisar, gerar PDF da ficha inicial com itens, prazos e docs requeridos para cotacao.

## Front
- Integrar telas para: importar lista de docs pre-cadastrados, atualizar status (pendente/possui/anexado), selecionar versao existente, upload especifico, criar doc custom e baixar arquivos.
- Exibir versoes de documento na ficha do processo; permitir escolher qual versao vincular.
- Tratar mensagens de permissão (403) ao gerenciar docs.
