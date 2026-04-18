# Melhorias no Fluxo de Empenhos

## 📋 Objetivo

Implementar melhorias no sistema de empenhos para seguir rigorosamente o fluxo descrito, onde o empenho funciona como o "nó central" que conecta a autorização de venda (feita no certame) com a execução financeira e a entrega real.

## ✅ Implementado

### 1. Cálculo Automático de Prazo de Entrega
- **Arquivo**: `app/Application/Empenho/UseCases/CriarEmpenhoUseCase.php`
- **Funcionalidade**: 
  - Calcula automaticamente `prazo_entrega_calculado` baseado na `data_recebimento` e `prazo_entrega` do processo
  - Faz parse de formatos como "30 dias", "2 meses", "90 dias"
  - O cálculo é feito no UseCase e também no Observer para garantir consistência

### 2. Observer Melhorado
- **Arquivo**: `app/Observers/EmpenhoObserver.php`
- **Melhorias**:
  - **Cálculo automático de prazo**: Recalcula `prazo_entrega_calculado` quando `data_recebimento` muda
  - **Atualização de situação**: Muda situação da AF/Contrato para "Atendendo" quando empenho é vinculado
  - **Recálculo de valores financeiros**: Atualiza valores financeiros dos itens vinculados ao empenho
  - **Sincronização de saldos**: Garante que saldos de Contrato/AF sejam atualizados

### 3. Efeitos Automáticos Implementados

#### Ao Criar/Atualizar Empenho:
1. ✅ **Cálculo de Prazos**: Calcula automaticamente `prazo_entrega_calculado` baseado em `data_recebimento + prazo_entrega` do processo
2. ✅ **Atualização de Situação**: 
   - Contrato: Muda para "Atendendo" quando há empenhos vinculados
   - AF: Situação atualizada automaticamente via `atualizarSaldo()` (já implementado)
3. ✅ **Reserva de Saldo**: Validação de quantidade disponível já implementada em `ProcessoItemVinculoService`
4. ✅ **Recálculo de Valores Financeiros**: Itens vinculados ao empenho têm seus valores financeiros recalculados automaticamente

#### Ao Vincular Empenho a Item:
1. ✅ **Validação de Quantidade**: Sistema valida que quantidade não excede disponível
2. ✅ **Atualização de Saldo**: Saldo do processo é atualizado dinamicamente
3. ✅ **Gatilho para Logística/Faturamento**: Empenho vinculado permite emissão de NF de saída

## 🔄 Fluxo Completo Implementado

### 1. Níveis de Vinculação ✅
- ✅ Vínculo com Contrato: Empenho pode ser "filho" do contrato
- ✅ Vínculo com AF: Empenho pode vincular-se à AF
- ✅ Vínculo Direto ao Processo: Empenho pode vincular-se diretamente ao processo

### 2. Entrada de Dados ✅
- ✅ Registro manual de número da Nota de Empenho, valor total e itens
- ✅ Cálculo automático de prazos baseado em `data_recebimento` e `prazo_entrega` do processo
- ✅ Validação de quantidade disponível

### 3. Efeitos Automáticos ✅
- ✅ **Saldo do Processo**: Calculado dinamicamente via `SaldoService`
- ✅ **Situação da AF/Contrato**: Atualizada automaticamente para "Atendendo"
- ✅ **Custo Estimado vs. Real**: Comparativo implementado via `calcularComparativoCustos()`

## 📝 Detalhes Técnicos

### Parse de Prazo de Entrega
O sistema aceita formatos como:
- "30 dias"
- "2 meses"
- "90 dias"
- "1 ano"

O parse é feito em:
- `CriarEmpenhoUseCase::parsePrazoEntrega()`
- `EmpenhoObserver::parsePrazoEntrega()`

### Atualização de Valores Financeiros
Quando um empenho é criado/atualizado:
1. Observer busca todos os `ProcessoItemVinculo` vinculados ao empenho
2. Para cada vínculo, chama `processoItem->atualizarValoresFinanceiros()`
3. Isso recalcula `valor_faturado`, `valor_pago`, `saldo_aberto` baseado nas NFs vinculadas

### Atualização de Situação
- **Contrato**: Quando há empenhos vinculados, situação muda para "Atendendo"
- **AF**: Situação atualizada automaticamente via `atualizarSaldo()`:
  - `aguardando_empenho`: Sem empenhos
  - `atendendo`: Com empenhos parciais
  - `concluida`: Saldo zerado

## 🎯 Benefícios

1. **Automação**: Cálculo de prazos e atualização de situações são automáticos
2. **Consistência**: Valores financeiros sempre atualizados quando empenho muda
3. **Rastreabilidade**: Sistema mantém histórico completo de vínculos
4. **Conformidade**: Segue rigorosamente a Lei 4.320/64 (Empenho, Liquidação e Pagamento)

## ⚠️ Notas Importantes

- O cálculo de prazo só funciona se o processo tiver `prazo_entrega` preenchido
- A situação do Contrato só muda para "Atendendo" se houver empenhos vinculados
- Os valores financeiros dos itens dependem de notas fiscais vinculadas aos contratos/AFs/empenhos

