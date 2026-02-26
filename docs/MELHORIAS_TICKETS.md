# Melhorias para o sistema de tickets de suporte

## Já implementado (nesta sessão)

- **Toasts no admin**: feedback ao salvar status e ao enviar resposta (sucesso/erro).
- **Busca na listagem admin**: filtro por número do ticket ou trecho da descrição (backend + frontend, com debounce 400ms).
- **Coluna "Respostas"**: exibe a quantidade de respostas na tabela da listagem admin.

---

## Sugestões de melhorias futuras

### UX / Frontend

| Melhoria | Descrição |
|----------|-----------|
| **Voltar preservando filtros** | No detalhe do ticket, o botão "Voltar" levar à lista com os mesmos filtros (empresa, status, busca e página). Usar state na navegação ou query params na URL da lista. |
| **Confirmação ao marcar como resolvido** | Ao mudar status para "Resolvido", exibir um modal ou confirmação antes de salvar. |
| **Ordenação na listagem** | Permitir ordenar por data, status ou número (ex.: dropdown "Ordenar por"). |
| **Filtro por data** | Ex.: "Abertos nos últimos 7 dias" ou intervalo de datas. |
| **Preview de imagem no Suporte** | Antes de enviar o ticket, mostrar preview da imagem anexa. |
| **Indicador de novas respostas (cliente)** | Na tela Suporte, indicar quando há novas respostas do admin (ex.: badge ou ícone). |
| **Skeleton loading** | Trocar spinner genérico por skeleton na listagem e no detalhe. |

### Backend / API

| Melhoria | Descrição |
|----------|-----------|
| **Notificação ao responder** | Quando o admin responde, enviar e-mail (ou notificação in-app) ao usuário que abriu o ticket. |
| **Categoria/assunto** | Campo opcional no ticket (ex.: "Bug", "Dúvida", "Cobrança") e filtro na listagem admin. |
| **Prioridade** | Campo opcional (baixa, média, alta) e filtro/ordenacao. |
| **Rate limit na criação** | Limitar quantos tickets o usuário pode abrir por período (ex.: 5 por hora) para evitar spam. |
| **Casts no model** | Garantir `created_at` e `updated_at` como datetime nos models SupportTicket e SupportTicketResponse. |
| **SLA (opcional)** | Registrar "tempo até primeira resposta" e exibir no admin. |

### Segurança / Robustez

| Melhoria | Descrição |
|----------|-----------|
| **Validação de anexo** | No upload do ticket, validar tipo e tamanho (imagem) e escopo de storage por tenant. |
| **Sanitização de mensagens** | Evitar XSS em `descricao` e `mensagem` (ex.: escape na exibição ou política de conteúdo). |

### Organização do código

| Melhoria | Descrição |
|----------|-----------|
| **Constantes de status** | Usar constantes/enum para `aberto`, `em_atendimento`, `resolvido` no backend e frontend. |
| **Hook useAdminTickets** | Extrair lógica da listagem (load, filtros, paginação) para um hook reutilizável. |

---

Para priorizar, começar por: notificação ao responder, voltar preservando filtros e confirmação ao marcar como resolvido.
