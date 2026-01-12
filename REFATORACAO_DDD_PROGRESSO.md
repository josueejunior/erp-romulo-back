# Progresso da Refatoração DDD - Controllers

## ✅ ProcessoController - PRINCIPAL (70% completo)

### ✅ **Completado:**
1. **Use Cases Criados:**
   - ✅ `CriarProcessoUseCase` (já existia, atualizado com validações de plano)
   - ✅ `AtualizarProcessoUseCase` 
   - ✅ `ExcluirProcessoUseCase`
   - ✅ `ListarProcessosUseCase`
   - ✅ `BuscarProcessoUseCase`
   - ✅ `MoverParaJulgamentoUseCase` (já existia, atualizado com validação de empresa)
   - ✅ `MarcarProcessoVencidoUseCase`
   - ✅ `MarcarProcessoPerdidoUseCase`
   - ✅ `ConfirmarPagamentoProcessoUseCase`
   - ✅ `BuscarHistoricoConfirmacoesUseCase`

2. **DTOs Criados:**
   - ✅ `CriarProcessoDTO` (já existia, atualizado para processar objetos)
   - ✅ `AtualizarProcessoDTO`
   - ✅ `ListarProcessosDTO`

3. **FormRequests Criados:**
   - ✅ `ProcessoCreateRequest`
   - ✅ `ProcessoUpdateRequest`
   - ✅ `ConfirmarPagamentoRequest` (já existia)

4. **Presenter Criado:**
   - ✅ `ProcessoApiPresenter`

5. **Métodos Refatorados:**
   - ✅ `list()` - Usa `ListarProcessosUseCase` e `ListarProcessosDTO`
   - ✅ `get()` - Usa `BuscarProcessoUseCase`
   - ✅ `store()` - Usa `ProcessoCreateRequest`, `CriarProcessoDTO`, `CriarProcessoUseCase`
   - ✅ `update()` - Usa `ProcessoUpdateRequest`, `AtualizarProcessoDTO`, `AtualizarProcessoUseCase`
   - ✅ `destroy()` - Usa `ExcluirProcessoUseCase`
   - ✅ `moverParaJulgamento()` - Usa `MoverParaJulgamentoUseCase`
   - ✅ `marcarVencido()` - Usa `MarcarProcessoVencidoUseCase`
   - ✅ `marcarPerdido()` - Usa `MarcarProcessoPerdidoUseCase`
   - ✅ `confirmarPagamento()` - Usa `ConfirmarPagamentoProcessoUseCase`
   - ✅ `historicoConfirmacoes()` - Usa `BuscarHistoricoConfirmacoesUseCase`

### ⚠️ **Pendente (métodos específicos):**
- ⚠️ `exportarFicha()` - Ainda faz serialização CSV manual no controller
- ⚠️ `downloadEdital()` - Ainda tem lógica HTTP complexa no controller
- ⚠️ `sugerirStatus()` - Ainda usa ProcessoService diretamente
- ⚠️ `importarDocumentos()`, `sincronizarDocumentos()`, `listarDocumentos()`, etc. - Já usam Use Cases mas ainda recebem Processo $processo

### 📝 **Notas:**
- ⚠️ Alguns Use Cases ainda trabalham com modelos Eloquent (`MarcarProcessoVencidoUseCase`, `ConfirmarPagamentoProcessoUseCase`, `BuscarHistoricoConfirmacoesUseCase`) porque precisam acessar relacionamentos. Idealmente, isso deveria estar no Repository ou em um Domain Service.
- ⚠️ `ProcessoStatusService` ainda trabalha com modelos Eloquent. Refatoração completa exigiria mover toda lógica de status para a entidade Processo.

---

## 📋 Próximos Controllers (Por Prioridade)

### 1. ProcessoItemController ❌❌❌ (ALTA PRIORIDADE)
**Problemas:**
- Usa `ProcessoItemService` diretamente
- `$item->update()` direto no controller
- Validações manuais no controller
- Acessa Eloquent diretamente

**Plano:**
- Criar Use Cases: `CriarProcessoItemUseCase`, `AtualizarProcessoItemUseCase`, `ExcluirProcessoItemUseCase`, `ListarProcessoItensUseCase`
- Criar DTOs
- Criar FormRequests
- Remover `$item->update()` direto

### 2. CustoIndiretoController ❌❌❌ (ALTA PRIORIDADE)
**Problemas:**
- Zero DDD
- Tudo via Service
- Sem DTOs, sem Resources

**Plano:**
- Criar Use Cases completos
- Criar DTOs
- Criar Resources

### 3. CalendarioController ❌❌ (MÉDIA PRIORIDADE)
**Problemas:**
- Gerencia cache no controller
- Validação de plano no controller (deveria ser middleware)

**Plano:**
- Mover cache para Use Cases
- Criar DTOs para filtros
- Criar Presenter

### 4. SaldoController ❌❌ (MÉDIA PRIORIDADE)
**Problemas:**
- Gerencia cache no controller
- Validações manuais

**Plano:**
- Mover cache para Use Cases
- Criar DTOs

### 5. ExportacaoController ❌ (MÉDIA PRIORIDADE)
**Problemas:**
- Lógica HTTP no controller

**Plano:**
- Criar Use Cases
- Criar Exporters

---

## 📊 Métricas

- **Controllers Refatorados:** 1/6 críticos (16%)
- **Métodos Refatorados:** 10/15 principais do ProcessoController (67%)
- **Use Cases Criados:** 10
- **DTOs Criados:** 3
- **FormRequests Criados:** 2







