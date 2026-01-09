# Análise de Controllers - Conformidade DDD

## ✅ Controllers que seguem DDD (Referência)

1. **EmpenhoController** - ✅ Excelente
   - Usa Use Cases para todas operações
   - Usa DTOs (ListarEmpenhosDTO, CriarEmpenhoDTO, AtualizarEmpenhoDTO)
   - Usa Presenter (EmpenhoApiPresenter) para serialização
   - Não acessa Eloquent diretamente
   - Controller apenas orquestra

2. **NotaFiscalController** - ✅ Bom
   - Usa Use Cases principais
   - Usa DTOs (FiltroNotaFiscalDTO, CriarNotaFiscalDTO, AtualizarNotaFiscalDTO)
   - ⚠️ Ainda mantém `NotaFiscalService` mas não usa para operações core

3. **OrcamentoController** - ✅ Bom
   - Usa Use Cases para operações principais
   - Usa DTOs
   - ⚠️ Ainda mantém `OrcamentoService` mas não usa para operações core

4. **FornecedorController** - ✅ Excelente
   - Usa Use Cases
   - Usa Resources para transformação
   - Usa DTOs

5. **OrgaoController** - ✅ Excelente
   - Usa Use Cases
   - Usa Resources
   - Usa DTOs

6. **UserController** - ✅ Excelente
   - Usa Use Cases
   - Usa Resources
   - Usa DTOs

7. **DashboardController** - ✅ Bom
   - Usa Use Case
   - Controller fino

8. **RelatorioController** - ✅ Bom
   - Usa Domain Service
   - Usa DTOs (RelatorioOrcamentosResult)
   - Usa Exporter Interface
   - Usa FormRequest

9. **FormacaoPrecoController** - ✅ Bom
   - Usa ResolvesContext trait
   - Usa Domain Exceptions
   - ⚠️ Ainda usa FormacaoPrecoService

10. **ContratoController** - ✅ Bom
    - Usa ResolvesContext trait
    - Usa Domain Exceptions
    - Usa Use Cases
    - ⚠️ Ainda usa ContratoService

11. **AutorizacaoFornecimentoController** - ✅ Bom
    - Usa ResolvesContext trait
    - Usa Domain Exceptions
    - ⚠️ Ainda usa AutorizacaoFornecimentoService

12. **NotificacaoController** - ✅ Bom
    - Usa Domain Service
    - Usa FormRequests
    - ⚠️ Retorna Collections do Service diretamente

---

## ❌ Controllers que NÃO seguem DDD (Precisam Refatoração)

### 1. **ProcessoController** ❌❌❌ CRÍTICO
**Problemas:**
- ❌ Ainda usa `ProcessoService` diretamente para `store`, `update`, `destroy`, `list`, `get`
- ❌ Validação no controller (deveria ser FormRequest)
- ❌ Acessa Eloquent diretamente: `Processo $processo`, `$processo->load(...)`
- ❌ Lógica de negócio no controller: `assertProcessoEmpresa()`, validações manuais
- ❌ Métodos como `historicoConfirmacoes()` fazem queries Eloquent diretas
- ❌ `exportarFicha()` faz serialização CSV manual no controller
- ❌ `downloadEdital()` tem lógica HTTP complexa no controller
- ❌ Cache gerenciado no controller (`RedisService`)
- ❌ Métodos como `moverParaJulgamento()`, `marcarVencido()` chamam service mas deveriam ser Use Cases

**Recomendações:**
- Criar Use Cases: `CriarProcessoUseCase`, `AtualizarProcessoUseCase`, `ExcluirProcessoUseCase`, `ListarProcessosUseCase`, `BuscarProcessoUseCase`
- Criar DTOs para entrada/saída
- Mover lógica de exportação para Exporters
- Mover cache para Use Cases
- Usar FormRequests para validação
- Remover acesso direto a Eloquent

---

### 2. **ProcessoItemController** ❌❌❌ CRÍTICO
**Problemas:**
- ❌ Usa `ProcessoItemService` diretamente
- ❌ Validações manuais no controller (`$request->validate()`)
- ❌ Acessa Eloquent diretamente: `Processo $processo`, `ProcessoItem $item`
- ❌ Lógica de negócio no controller: múltiplas validações manuais
- ❌ Métodos `atualizarValorFinalDisputa()`, `atualizarValorNegociado()`, `atualizarStatus()` fazem `$item->update()` diretamente
- ❌ Validação de propriedade no controller (`empresa_id !== $empresa->id`)
- ❌ Retorna modelos Eloquent diretamente

**Recomendações:**
- Criar Use Cases: `CriarProcessoItemUseCase`, `AtualizarProcessoItemUseCase`, `ExcluirProcessoItemUseCase`, `ListarProcessoItensUseCase`
- Criar DTOs
- Mover validações para FormRequests
- Remover `$item->update()` direto, usar Use Cases
- Usar Domain Exceptions para validações de propriedade

---

### 3. **CalendarioController** ❌❌
**Problemas:**
- ❌ Gerencia cache diretamente no controller (`RedisService`)
- ❌ Validação de plano no controller (deveria ser middleware)
- ❌ Parse de datas no controller (`Carbon::parse()`)
- ❌ Retorna Collections do Service diretamente
- ❌ Não usa DTOs para filtros
- ⚠️ Usa `CalendarioService` (aceitável se Service for Domain Service, mas deveria ter Use Cases)

**Recomendações:**
- Criar Use Cases que gerenciem cache internamente
- Mover validação de plano para middleware
- Criar DTOs para filtros de calendário
- Criar Presenter/Resource para serialização

---

### 4. **SaldoController** ❌❌
**Problemas:**
- ❌ Gerencia cache diretamente no controller (`RedisService`)
- ❌ Validações manuais no controller
- ❌ Acessa Eloquent diretamente: `Processo $processo`
- ⚠️ Usa `SaldoService` (aceitável se for Domain Service, mas deveria ter Use Cases)

**Recomendações:**
- Criar Use Cases que gerenciem cache internamente
- Criar DTOs para resultados de saldo
- Remover acesso direto a Eloquent
- Usar Domain Exceptions para validações

---

### 5. **ExportacaoController** ❌
**Problemas:**
- ❌ Validações manuais no controller
- ❌ Acessa Eloquent diretamente: `Processo $processo`
- ❌ Lógica de formatação HTTP no controller (headers, content-type)
- ⚠️ Usa `ExportacaoService` (aceitável, mas deveria ter Use Cases)

**Recomendações:**
- Criar Use Cases para exportações
- Criar Exporters para diferentes formatos
- Usar Domain Exceptions
- Remover acesso direto a Eloquent

---

### 6. **CustoIndiretoController** ❌❌❌ CRÍTICO
**Problemas:**
- ❌ Usa `CustoIndiretoService` diretamente
- ❌ Validação no Service, não em FormRequest
- ❌ Retorna modelos Eloquent diretamente
- ❌ Não usa DTOs
- ❌ Não usa Resources
- ❌ Validações dentro do controller via service (`validateStoreData()`)

**Recomendações:**
- Criar Use Cases: `CriarCustoIndiretoUseCase`, `AtualizarCustoIndiretoUseCase`, `ExcluirCustoIndiretoUseCase`, `ListarCustosIndiretosUseCase`
- Criar DTOs
- Criar Resources para transformação
- Criar FormRequests para validação
- Remover acesso direto a Service

---

### 7. **ProcessoController::historicoConfirmacoes()** ❌❌❌ CRÍTICO
**Método específico com problemas graves:**
- ❌ Faz queries Eloquent diretas: `$processo->itens`, `NotaFiscal::where()`
- ❌ Lógica de negócio no controller (cálculos de receita, custos)
- ❌ Serialização manual de arrays
- ❌ Não usa Use Case nem Repository

**Recomendações:**
- Criar `BuscarHistoricoConfirmacoesUseCase`
- Criar DTO para resultado (`HistoricoConfirmacoesDTO`)
- Mover queries para Repository
- Mover cálculos para Domain Service

---

### 8. **ProcessoController::exportarFicha()** ❌❌❌ CRÍTICO
**Método específico com problemas graves:**
- ❌ Serialização CSV manual no controller
- ❌ Acessa relacionamentos Eloquent diretamente: `$processo->itens()`, `$processo->orgao`, `$processo->setor`
- ❌ Lógica de formatação no controller
- ❌ Não usa Exporter

**Recomendações:**
- Criar `ExportarFichaProcessoUseCase`
- Criar `FichaProcessoCsvExporter` implementando `ExporterInterface`
- Mover lógica de formatação para Exporter

---

### 9. **ProcessoController::downloadEdital()** ❌❌
**Método específico com problemas:**
- ❌ Lógica HTTP complexa no controller (stream_context, headers, file_get_contents)
- ❌ Tratamento de erros misturado com lógica de negócio
- ❌ Não usa Use Case

**Recomendações:**
- Criar `BaixarEditalUseCase`
- Criar Service para download HTTP (Infrastructure Layer)
- Usar Domain Exceptions

---

### 10. **ProcessoItemController::atualizarValorFinalDisputa()** ❌❌
**Métodos específicos com problemas:**
- ❌ `$item->update()` direto no controller
- ❌ Validação no controller
- ❌ Não usa Use Case

**Mesmo problema em:**
- `atualizarValorNegociado()`
- `atualizarStatus()`

**Recomendações:**
- Criar `AtualizarValorFinalDisputaUseCase`
- Criar `AtualizarValorNegociadoUseCase`
- Criar `AtualizarStatusItemUseCase`
- Criar FormRequests para cada um

---

## 📊 Resumo por Prioridade

### 🔴 ALTA PRIORIDADE (Impacto Crítico)
1. **ProcessoController** - Controller mais usado, violações graves
2. **ProcessoItemController** - Violações graves, updates diretos
3. **CustoIndiretoController** - Zero DDD

### 🟡 MÉDIA PRIORIDADE (Impacto Moderado)
4. **CalendarioController** - Cache e validações no controller
5. **SaldoController** - Cache e validações no controller
6. **ExportacaoController** - Lógica de formatação HTTP

### 🟢 BAIXA PRIORIDADE (Melhorias Incrementais)
7. **NotaFiscalController** - Remover `NotaFiscalService` residual
8. **OrcamentoController** - Remover `OrcamentoService` residual
9. **FormacaoPrecoController** - Remover `FormacaoPrecoService` residual
10. **ContratoController** - Remover `ContratoService` residual
11. **AutorizacaoFornecimentoController** - Remover `AutorizacaoFornecimentoService` residual

---

## 🎯 Padrão a Seguir (Baseado em EmpenhoController)

```php
class ProcessoController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        private CriarProcessoUseCase $criarProcessoUseCase,
        private AtualizarProcessoUseCase $atualizarProcessoUseCase,
        private ExcluirProcessoUseCase $excluirProcessoUseCase,
        private ListarProcessosUseCase $listarProcessosUseCase,
        private BuscarProcessoUseCase $buscarProcessoUseCase,
        private ProcessoApiPresenter $presenter,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    public function list(Request $request): JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $dto = ListarProcessosDTO::fromRequest($request->all(), $empresa->id);
        $paginado = $this->listarProcessosUseCase->executar($dto);
        
        $models = collect($paginado->items())->map(fn($domain) => 
            $this->processoRepository->buscarModeloPorId($domain->id, ['orgao', 'setor'])
        )->filter();
        
        return response()->json([
            'data' => $this->presenter->presentCollection($models),
            'meta' => [...]
        ]);
    }

    public function store(ProcessoCreateRequest $request): JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $dto = CriarProcessoDTO::fromRequest($request->validated(), $empresa->id);
        
        $processoDomain = $this->criarProcessoUseCase->executar($dto);
        $processoModel = $this->processoRepository->buscarModeloPorId($processoDomain->id);
        
        return response()->json([
            'data' => $this->presenter->present($processoModel)
        ], 201);
    }
}
```

---

## ✅ Checklist DDD para Controllers

- [ ] Usa Use Cases em vez de Services diretamente
- [ ] Usa DTOs para entrada e saída
- [ ] Usa FormRequests para validação
- [ ] Não acessa Eloquent diretamente (apenas via Repository)
- [ ] Não tem lógica de negócio no controller
- [ ] Não gerencia cache no controller
- [ ] Usa Presenter/Resource para serialização
- [ ] Usa Domain Exceptions para erros de negócio
- [ ] Controller apenas orquestra (Request → DTO → Use Case → Presenter → Response)




