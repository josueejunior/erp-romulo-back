# 📋 Feedback da Transcrição - Plano de Implementação

## ✅ Campos que JÁ EXISTEM no Backend

Os seguintes campos já estão implementados no modelo `Processo`:
- ✅ `tipo_selecao_fornecedor` (menor_preco_item, menor_preco_lote)
- ✅ `tipo_disputa` (aberto, aberto_fechado)
- ✅ `endereco_entrega`
- ✅ `forma_entrega` (parcelado, remessa_unica)
- ✅ `prazo_entrega`
- ✅ `prazo_pagamento`
- ✅ `validade_proposta`
- ✅ `numero_processo_administrativo`
- ✅ `data_recebimento_pagamento` (para encerramento)

## 🔨 Ações Necessárias

### 1. Frontend - Formulário de Processo
- [ ] Adicionar campos faltantes no formulário React:
  - Tipo de seleção de fornecedor (select)
  - Tipo de disputa (select)
  - Endereço de entrega (já existe?)
  - Forma de entrega (select: parcelado/remessa única)
  - Prazo de entrega (texto + opção dias úteis/corridos)
  - Prazo de pagamento (texto)
  - Validade da proposta (texto)
  - Atestado de capacidade técnica (no item)

### 2. Status em Participação
- [ ] Adicionar campo `status_participacao` com opções:
  - normal
  - adiado
  - suspenso
  - nao_vai_acontecer
- [ ] Interface para atualizar status na aba de participação

### 3. Dashboard - Contadores por Etapa
- [ ] Adicionar cards mostrando:
  - Processos em Participação
  - Processos em Julgamento
  - Processos em Execução
  - Processos em Pagamento
  - Processos em Encerramento

### 4. Calendário - Filtros
- [ ] Adicionar filtros:
  - Calendário de Participação
  - Calendário de Julgamento
  - Ambos (padrão)

### 5. Orçamentos - Melhorias
- [ ] Sistema já permite vincular ao processo (storeByProcesso)
- [ ] Verificar se permite editar especificação técnica
- [ ] Verificar se permite excluir itens do orçamento
- [ ] Adicionar transportadora no orçamento (já existe transportadora_id)

### 6. Formação de Preço
- [ ] Garantir que funciona na fase de participação
- [ ] Mostrar valor mínimo de venda no calendário

### 7. Disputa - Valor Arrematado
- [ ] Adicionar campo `valor_arrematado` na disputa
- [ ] Usar esse valor na geração da proposta comercial

### 8. Custos Indiretos
- [ ] Verificar se módulo já existe (CustoIndiretoController)
- [ ] Adicionar no menu entre Fornecedores

### 9. Encerramento
- [ ] Garantir que só processos com `data_recebimento_pagamento` entram na gestão financeira
- [ ] Verificar RelatorioFinanceiroController

### 10. Hierarquia de Documentos
- [ ] Verificar se notas fiscais já estão vinculadas a Contrato/AF/Empenho
- [ ] Garantir que não há vínculo direto com processo

## 📝 Observações Importantes

1. **Orçamentos**: O sistema já suporta orçamentos vinculados ao processo (não só item)
2. **Documentos**: Sistema já tem estrutura de documentos de habilitação
3. **Valor Arrematado**: Precisa ser adicionado na disputa
4. **Custos Indiretos**: Módulo existe, precisa verificar se está no menu

