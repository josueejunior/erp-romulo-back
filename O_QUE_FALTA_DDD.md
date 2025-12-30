# 📋 O Que Ainda Falta para DDD Completo

## ✅ JÁ REFATORADOS (Acesso Direto Removido)

1. ✅ **AuthController** - Usa `BuscarAdminUserPorEmailUseCase`
2. ✅ **PaymentController** - Usa `PlanoRepositoryInterface`
3. ✅ **WebhookController** - Usa `AssinaturaRepositoryInterface` e `PaymentLogRepositoryInterface`

---

## 🟡 ESTRUTURA DDD PARCIAL (Tem Use Cases/Repositories mas Controller Não Usa)

### 1. **OrgaoController** 
- ✅ **Tem**: `CriarOrgaoUseCase`, `OrgaoRepositoryInterface`
- ❌ **Problema**: Controller usa apenas `OrgaoService`, não usa os Use Cases
- **Ação**: Refatorar controller para usar `CriarOrgaoUseCase` e `OrgaoRepositoryInterface`
- **Falta**: Use Cases para `list`, `update`, `delete`

### 2. **SetorController**
- ✅ **Tem**: `CriarSetorUseCase`, `SetorRepositoryInterface`
- ❌ **Problema**: Controller usa apenas `SetorService`, não usa os Use Cases
- **Ação**: Refatorar controller para usar `CriarSetorUseCase` e `SetorRepositoryInterface`
- **Falta**: Use Cases para `list`, `update`, `delete`

---

## 🔴 SEM ESTRUTURA DDD (Precisa Criar Tudo)

### 3. **CustoIndiretoController**
- ❌ **Não tem**: Use Cases, Repository DDD, Entities
- ✅ **Tem**: Apenas `CustoIndiretoService`
- **Ação**: Criar estrutura DDD completa:
  - `Domain/Custo/Entities/CustoIndireto.php`
  - `Domain/Custo/Repositories/CustoIndiretoRepositoryInterface.php`
  - `Application/Custo/UseCases/CriarCustoIndiretoUseCase.php`
  - `Application/Custo/UseCases/AtualizarCustoIndiretoUseCase.php`
  - `Application/Custo/UseCases/ListarCustosIndiretosUseCase.php`
  - `Application/Custo/DTOs/*.php`
  - `Infrastructure/Persistence/Eloquent/CustoIndiretoRepository.php`

### 4. **DocumentoHabilitacaoController**
- ❌ **Não tem**: Use Cases, Repository DDD, Entities
- ✅ **Tem**: Apenas `DocumentoHabilitacaoService`
- **Ação**: Criar estrutura DDD completa:
  - `Domain/Documento/Entities/DocumentoHabilitacao.php`
  - `Domain/Documento/Repositories/DocumentoHabilitacaoRepositoryInterface.php`
  - `Application/Documento/UseCases/*.php`
  - `Application/Documento/DTOs/*.php`
  - `Infrastructure/Persistence/Eloquent/DocumentoHabilitacaoRepository.php`

---

## 🟢 USAM SERVICES (Decisão Arquitetural - Podem Ficar Assim)

Estes controllers usam Services, mas isso pode ser uma decisão arquitetural válida para módulos menos críticos:

- **ProcessoController** - Usa `ProcessoService` (módulo complexo, pode manter Service)
- **DashboardController** - Usa `DashboardService` (apenas agregação de dados)
- **CalendarioController** - Usa `CalendarioService` (apenas agregação de dados)
- **RelatorioFinanceiroController** - Usa `FinanceiroService` (apenas relatórios)

**Nota**: Estes podem ser refatorados no futuro se necessário, mas não são críticos.

---

## 📊 RESUMO POR PRIORIDADE

### 🔴 Alta Prioridade (Criar Estrutura DDD)
1. **CustoIndiretoController** - Sem estrutura DDD
2. **DocumentoHabilitacaoController** - Sem estrutura DDD

### 🟡 Média Prioridade (Integrar DDD Existente)
3. **OrgaoController** - Tem DDD mas não usa
4. **SetorController** - Tem DDD mas não usa

### 🟢 Baixa Prioridade (Opcional)
5. **ProcessoController** - Pode manter Service (módulo complexo)
6. **DashboardController** - Pode manter Service (apenas agregação)
7. **CalendarioController** - Pode manter Service (apenas agregação)
8. **RelatorioFinanceiroController** - Pode manter Service (apenas relatórios)

---

## 🎯 PRÓXIMOS PASSOS RECOMENDADOS

1. **Criar estrutura DDD para CustoIndireto** (alta prioridade)
2. **Criar estrutura DDD para DocumentoHabilitacao** (alta prioridade)
3. **Refatorar OrgaoController para usar Use Cases existentes** (média prioridade)
4. **Refatorar SetorController para usar Use Cases existentes** (média prioridade)

