# Arquitetura de Processos Licitatórios - O que falta implementar

## Status e Fases do Processo

### Status atuais
- `participacao` ✓ (criação de processo)
- `julgamento_habilitacao` ✓ (após sessão pública)
- `execucao` ✓ (após vencimento)
- `perdido` ✓ (arquivado)

### Contexto de Middleware
- ✓ `TenantContext` (empresa_id)
- ✓ `EnsureEmpresaAtivaContext` (ativa contexto)
- ✓ Auditoria de documentos (IP, user, ação)
- ✓ Permissões (canManageDocuments)

---

## 1. Fase de Participação/Cotação

### 1.1 Orçamento (novo módulo)
**Modelo**: `Orcamento`
```
Fields:
- id, empresa_id, processo_id, fornecedor_id
- data_criacao, data_atualizacao, ativo
```

**Serviço**: `OrcamentoService`
- Criar orçamento para fornecedor em processo específico
- Listar orçamentos por processo
- Atualizar orçamento

**Controller**: `OrcamentoController`
- POST `/processos/{id}/orcamentos` (criar)
- GET `/processos/{id}/orcamentos` (listar)
- PUT `/processos/{id}/orcamentos/{orcamentoId}` (atualizar)
- DELETE `/processos/{id}/orcamentos/{orcamentoId}` (deletar)

### 1.2 Item de Orçamento por Fornecedor
**Modelo**: `OrcamentoItem`
```
Fields:
- id, orcamento_id, processo_item_id
- especificacao_tecnica_customizada (nullable)
- marca_modelo_referencia (nullable)
- observacoes
```

**Funcionalidade**:
- Herdar especificação técnica do ProcessoItem
- Permitir customização por fornecedor
- Permitir indicação de marca/modelo

### 1.3 Fornecedor Escolhido por Item
**Modelo**: `ProcessoItemFornecedor`
```
Fields:
- id, processo_id, processo_item_id, orcamento_id
- fornecedor_escolhido (bool)
- data_escolha
```

**Serviço**: `ProcessoItemFornecedorService`
- Marcar orçamento como fornecedor escolhido para item
- Desmarcar anterior automaticamente
- Atualizar campos customizáveis

---

## 2. Formação de Preços

### 2.1 Tabela de Formação de Preço
**Modelo**: `FormacaoPreco`
```
Fields:
- id, processo_id, processo_item_id, orcamento_id
- custo_material (decimal)
- custo_frete (decimal)
- imposto_percentual (decimal)
- margem_lucro_percentual (decimal)
- valor_minimo_venda (decimal, calculado)
- data_criacao
```

**Serviço**: `FormacaoPrecoService`
- Criar formulário a partir de orçamento
- Calcular valor mínimo: `(custo_material + custo_frete) * (1 + imposto%) / (1 - margem_lucro%)`
- Atualizar e salvar
- Auditar alterações

**Controller**: `FormacaoPrecoController`
- POST `/processos/{id}/formacao-preco` (criar)
- GET `/processos/{id}/formacao-preco/{itemId}` (obter)
- PUT `/processos/{id}/formacao-preco/{itemId}` (atualizar, recalcula automaticamente)

### 2.2 Exibição no Calendário
- Campo `valor_minimo_venda` visível no card de disputa
- Indicador visual: "Vender até R$ X" em destaque

---

## 3. Calendário de Disputas

### 3.1 Modelo/View: Calendário
- Filtro por status `participacao`
- Agrupamento por data da sessão pública
- Exibição: data, hora, número, órgão, objeto, valor mínimo (FormacaoPreco)
- Clique abre detalhe do processo

### 3.2 Endpoint
- GET `/processos/calendario-disputas` (com filtros: data_inicio, data_fim, orgao_id, etc)

---

## 4. Fase de Disputa (Participação)

### 4.1 Inclusão de Valor Final após Lances
**Campo novo em ProcessoItem**:
```
- valor_final_pos_disputa (decimal, nullable)
```

**Serviço**: Adicionar método `atualizarValorFinalDisputa()`

**Controller**:
- PATCH `/processos/{id}/itens/{itemId}/valor-final-disputa`

### 4.2 Transição Automática de Status
**Scheduler/Command**:
- `processos:atualizar-status-automatico` (executar a cada minuto ou periódico)
- Verificar se `data_hora_sessao_publica <= now()` e status = `participacao`
- Alterar para `julgamento_habilitacao`
- Log de auditoria

---

## 5. Exportação de Documentos

### 5.1 Proposta Comercial
**Controller**: Novo método em `ProcessoController`
- GET `/processos/{id}/exportar/proposta-comercial`
- Retorna HTML/PDF com:
  - Cabeçalho: empresa, órgão, processo, data
  - Itens: quantidade, especificação, valor final, marca/modelo
  - Validade proposta: `validade_proposta_inicio` até `validade_proposta_fim` (proporcional à data de geração)
  - Observações, prazos, condições

**Validação**:
- Status = `julgamento_habilitacao` ou `execucao`
- Conter pelo menos um item com status Aceito

### 5.2 Catálogo/Ficha Técnica
**Controller**: GET `/processos/{id}/exportar/catalogo-ficha-tecnica`
- Similar à proposta, mas foco em especificações técnicas
- Marca/modelo por item
- Comparação de especificações customizadas vs originais

---

## 6. Calendário de Julgamento

### 6.1 Modelo/View
- Filtro por status `julgamento_habilitacao`
- Agrupamento por data relevante (sessão ou data estimada)
- Exibição: número, órgão, itens com status, chance de arremate, lembretes

### 6.2 Endpoint
- GET `/processos/calendario-julgamento` (com filtros)

---

## 7. Fase de Julgamento (Julgamento e Habilitação)

### 7.1 Classificação da Empresa por Item
**Modelo novo**: `ProcessoItemClassificacao`
```
Fields:
- id, processo_item_id, classificacao (varchar, ex: "1º lugar", "2º lugar", "Desclassificado")
- observacoes (text)
- chance_arremate (bool)
- data_criacao, user_id
```

**Controller**:
- PATCH `/processos/{id}/itens/{itemId}/classificacao`
  - Payload: `{ classificacao, observacoes, chance_arremate }`

### 7.2 Lembrete de Arremate
**Modelo novo**: `ProcessoItemLembrete`
```
Fields:
- id, processo_item_id, data_lembrete, descricao, criado_por
```

**Serviço**: 
- Criar lembrete apenas se `chance_arremate = true`
- Scheduler para disparar notificação/email na data

**Controller**:
- POST `/processos/{id}/itens/{itemId}/lembrete`
- GET `/processos/{id}/itens/{itemId}/lembrete`
- DELETE `/processos/{id}/itens/{itemId}/lembrete`

### 7.3 Novo Valor Negociado
**Campo novo em ProcessoItem**:
```
- valor_negociado_pos_julgamento (decimal, nullable)
```

**Controller**:
- PATCH `/processos/{id}/itens/{itemId}/valor-negociado`

### 7.4 Status de Habilitação
**Campo novo em ProcessoItem**:
```
- status_item (enum: 'pendente', 'aceito', 'aceito_habilitado', 'desclassificado', 'inabilitado')
```

**Serviço**: `ProcessoStatusService`
- Validar transições de status por item
- Log de cada mudança

**Controller**:
- PATCH `/processos/{id}/itens/{itemId}/status`
  - Payload: `{ status_item }`

### 7.5 Lógica de Sugestão (Perda vs Vencimento)
**Serviço**: Novo método em `ProcessoStatusService`
```
sugerirMudancaStatus(processo):
  - Se todos items = 'desclassificado' || 'inabilitado' → return 'perdido' (com sugestão)
  - Se algum item = 'aceito' || 'aceito_habilitado' → return 'vencido' (com confirmação manual)
```

**Controller**: GET `/processos/{id}/sugerir-status` já existe, melhorar lógica

---

## 8. Fase de Perda (Arquivamento)

### 8.1 Marcar como Perdido
**Controller**: POST `/processos/{id}/marcar-perdido` já existe

**Lógica**:
- Validar se todos itens estão desclassificado/inabilitado
- Atualizar status → `arquivado` (ou novo status `perdido`?)
- Log de auditoria
- Notificar usuário

---

## 9. Fase de Vencimento (Execução)

### 9.1 Marcar como Vencido
**Controller**: POST `/processos/{id}/marcar-vencido` já existe

**Lógica**:
- Validar se há pelo menos um item Aceito/Aceito_Habilitado
- Atualizar status → `execucao`
- Log de auditoria

---

## 10. Fase de Execução - Vínculos com Documentos/Contratos/AF/Empenhos/NF

### 10.1 Contrato (novo módulo)
**Modelo**: `Contrato`
```
Fields:
- id, empresa_id, processo_id, numero_contrato
- data_assinatura, data_vigencia_inicio, data_vigencia_fim
- condicoes_prazos, condicoes_comerciais
- status ('ativo', 'vencido', 'encerrado')
- tipo ('normal', 'SRP')
- saldo (decimal)
- data_criacao, user_id
```

**Serviço**: `ContratoService`
- Criar contrato a partir de processo em execução
- Preenchimento automático dos dados do processo
- Preenchimento manual dos dados do contrato recebido
- Atualizar saldo conforme empenhos

**Controller**: `ContratoController`
- POST `/processos/{id}/contratos`
- GET `/processos/{id}/contratos`
- PUT `/processos/{id}/contratos/{contratoId}`
- GET `/contratos/{id}` (detalhes)

### 10.2 Autorização de Fornecimento (AF)
**Modelo**: `AutorizacaoFornecimento`
```
Fields:
- id, empresa_id, processo_id, numero_af
- status ('pendente', 'aguardando_empenho', 'atendendo_empenho', 'concluida')
- data_criacao, user_id
- saldo (decimal)
```

**Serviço**: `AutorizacaoFornecimentoService`
- Criar AF a partir de processo em execução
- Atualizar status baseado em empenhos
- Atualizar saldo

**Controller**: `AutorizacaoFornecimentoController`
- POST `/processos/{id}/autorizacoes-fornecimento`
- GET `/processos/{id}/autorizacoes-fornecimento`
- PUT `/processos/{id}/autorizacoes-fornecimento/{afId}`

### 10.3 Empenho
**Modelo**: `Empenho` (já deve existir)
```
Fields:
- id, empresa_id, contrato_id, autorizacao_fornecimento_id, processo_id
- numero_empenho, data_empenho, data_recebimento
- status ('pendente', 'atendendo', 'concluido')
- data_prazo_atendimento
- saldo (decimal)
```

**Serviço**: `EmpenhoService`
- Criar empenho a partir de contrato/AF
- Validar prazos (comparar com contrato/AF)
- Atualizar status "atendendo/concluído" conforme NF-e saída
- Atualizar saldo conforme NF-e entrada

**Controller**: `EmpenhoController`
- POST `/processos/{id}/empenhos` (ou `/contratos/{id}/empenhos`)
- GET `/processos/{id}/empenhos`
- PUT `/processos/{id}/empenhos/{empenhoId}`

### 10.4 Nota Fiscal de Entrada
**Modelo**: `NotaFiscalEntrada`
```
Fields:
- id, empresa_id, empenho_id, processo_id, fornecedor_id
- numero_nf, data_emissao, data_recebimento
- itens (relacionamento com ProcessoItem ou EmpenhoItem)
- custo_total (decimal)
- status ('pendente', 'recebida', 'atendida')
- comprovante_pagamento (arquivo), recibo (arquivo)
```

**Serviço**: `NotaFiscalEntradaService`
- Preenchimento automático a partir de empenho
- Preenchimento manual dos custos atualizados
- Atualizar status conforme NF-e saída
- Auditar alterações de custo

**Controller**: `NotaFiscalEntradaController`
- POST `/processos/{id}/notas-fiscais-entrada`
- GET `/processos/{id}/notas-fiscais-entrada`
- PUT `/processos/{id}/notas-fiscais-entrada/{nfeId}`

### 10.5 Nota Fiscal de Saída
**Modelo**: `NotaFiscalSaida`
```
Fields:
- id, empresa_id, empenho_id, processo_id
- numero_nf, data_emissao, data_entrega
- status_logistica ('pendente', 'em_transito', 'entregue')
- observacoes_logistica
```

**Serviço**: `NotaFiscalSaidaService`
- Integração com emissor de notas (preenchimento automático)
- Atualizar status logística manualmente
- Vincular com NF-e entrada para calcular lucro

**Controller**: `NotaFiscalSaidaController`
- POST `/processos/{id}/notas-fiscais-saida`
- GET `/processos/{id}/notas-fiscais-saida`
- PATCH `/processos/{id}/notas-fiscais-saida/{nfsId}/status-logistica`

---

## 11. Gestão Financeira e Saldos

### 11.1 Saldo do Processo
**Conceito**: `valor_vencido` (valor final após julgamento que a empresa deve receber)

**Cálculo**:
- Soma de itens com status Aceito/Aceito_Habilitado
- Valor = `valor_negociado_pos_julgamento` ou `valor_final_pos_disputa` ou `valor_estimado`

**Serviço**: `ProcessoSaldoService`
- Calcular saldo total
- Calcular saldo vinculado (contrato/AF)
- Calcular saldo empenhado
- Calcular saldo atendido por NF-e
- Descontar confirmações de pagamento

**Controller**: 
- GET `/processos/{id}/saldo` (total e por tipo)
- GET `/processos/{id}/saldo-vinculado` (em contratos/AF)
- GET `/processos/{id}/saldo-empenhado` (em empenhos)
- GET `/processos/{id}/saldo-atendido` (em NF-e)

### 11.2 Confirmação de Pagamento
**Modelo**: `ConfirmacaoPagamento`
```
Fields:
- id, processo_id, data_confirmacao, valor_confirmado
- user_id, data_criacao
```

**Lógica**:
- Ao confirmar pagamento, descontar do saldo do processo
- Registrar no log de transações

### 11.3 Custos Indiretos
**Modelo**: `CustoIndireto`
```
Fields:
- id, empresa_id, processo_id, descricao
- valor (decimal), data_custo, tipo (aluguel, energia, etc)
- data_criacao, user_id
```

**Serviço**: `CustoIndiretoService`
- Criar e listar custos indiretos

### 11.4 Relatório de Saúde Financeira
**Endpoints**:
- GET `/processos/{id}/financeiro/resumo` (por processo)
  - Saldo inicial, vinculado, empenhado, atendido
  - Custo direto (NF-e entrada)
  - Custo indireto
  - Lucro bruto/líquido
  - Margens
  
- GET `/processos/financeiro/por-periodo` (por período)
  - Filtro: data_inicio, data_fim, tipo_custo (direto/indireto)
  - Agregações por processo
  - Totais da empresa

---

## 12. Auditorias e Notificações

### 12.1 Auditoria de Mudanças
**Expansão de `DocumentoHabilitacaoLog`**:
- Aplicar padrão similar para Orçamento, ProcessoItem, FormacaoPreco, Contrato, etc
- Ação: 'criar', 'atualizar', 'excluir', 'mudar_status'
- user_id, IP, user_agent, timestamp

### 12.2 Notificações (opcional)
- Lembrete de arremate (ProcessoItemLembrete)
- Alerta de vencimento de proposta
- Alerta de atraso de empenho (comparar prazo vs data)
- Email/webhook ao mudar status de processo

---

## Resumo de Modelos Novos

| Modelo | Status | Descrição |
|--------|--------|-----------|
| Orcamento | ❌ | Orçamento por fornecedor em processo |
| OrcamentoItem | ❌ | Itens customizados no orçamento |
| ProcessoItemFornecedor | ❌ | Fornecedor escolhido por item |
| FormacaoPreco | ❌ | Calculadora de preço mínimo |
| ProcessoItemClassificacao | ❌ | Classificação da empresa no julgamento |
| ProcessoItemLembrete | ❌ | Lembrete de arremate |
| Contrato | ❌ | Contrato gerado (normal ou SRP) |
| AutorizacaoFornecimento | ❌ | AF gerada |
| Empenho | ❌ | Empenho (pode já existir) |
| NotaFiscalEntrada | ❌ | NF-e entrada de custo |
| NotaFiscalSaida | ❌ | NF-e saída de entrega |
| ConfirmacaoPagamento | ❌ | Confirmação de pagamento (pode já existir) |
| CustoIndireto | ❌ | Custos indiretos da empresa |

---

## Próximos Passos Sugeridos

1. **Curto prazo**: Orçamento, FormacaoPreco, Calendários, Valores em Disputa/Julgamento
2. **Médio prazo**: Exportação de Documentos (Proposta, Catálogo), Classificação e Status de Habilitação
3. **Longo prazo**: Contratos, AF's, Empenhos, NF's, Gestão Financeira Completa
