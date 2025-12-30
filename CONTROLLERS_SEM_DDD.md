# 📋 Controllers que Ainda Não Usam DDD Completamente

## ✅ REFATORADOS (Acesso Direto a Modelos Removido)

### 1. **AuthController** ✅
- **Antes**: `AdminUser::where('email', $validated['email'])->first();`
- **Agora**: Usa `BuscarAdminUserPorEmailUseCase` e `AdminUserRepositoryInterface`
- **Status**: ✅ **CONCLUÍDO**

### 2. **PaymentController** ✅
- **Antes**: `Plano::findOrFail($validated['plano_id']);`
- **Agora**: Usa `PlanoRepositoryInterface::buscarModeloPorId()`
- **Status**: ✅ **CONCLUÍDO**

### 3. **WebhookController** ✅
- **Antes**: `Assinatura::where()` e `PaymentLog::where()`
- **Agora**: Usa `AssinaturaRepositoryInterface` e `PaymentLogRepositoryInterface`
- **Status**: ✅ **CONCLUÍDO**

---

## 🟡 Média Prioridade (Usam Apenas Services, Não DDD)

### 4. **CustoIndiretoController** (`app/Modules/Custo/Controllers/CustoIndiretoController.php`)
- **Status**: Usa apenas `CustoIndiretoService`
- **Falta**: Use Cases e Repository DDD
- **Ação**: Criar estrutura DDD completa

### 5. **OrgaoController** (`app/Modules/Orgao/Controllers/OrgaoController.php`)
- **Status**: Usa apenas `OrgaoService`
- **Falta**: Use Cases e Repository DDD
- **Ação**: Criar estrutura DDD completa

### 6. **DocumentoHabilitacaoController** (`app/Modules/Documento/Controllers/DocumentoHabilitacaoController.php`)
- **Status**: Usa apenas `DocumentoHabilitacaoService`
- **Falta**: Use Cases e Repository DDD
- **Ação**: Criar estrutura DDD completa

### 7. **ProcessoController** (`app/Modules/Processo/Controllers/ProcessoController.php`)
- **Status**: Usa apenas Services (`ProcessoService`, `ProcessoStatusService`, etc.)
- **Falta**: Use Cases e Repository DDD
- **Ação**: Criar estrutura DDD completa

### 8. **DashboardController** (`app/Modules/Dashboard/Controllers/DashboardController.php`)
- **Status**: Usa apenas `DashboardService`
- **Falta**: Use Cases e Repository DDD
- **Ação**: Criar estrutura DDD completa

### 9. **CalendarioController** (`app/Modules/Calendario/Controllers/CalendarioController.php`)
- **Status**: Usa apenas `CalendarioService`
- **Falta**: Use Cases e Repository DDD
- **Ação**: Criar estrutura DDD completa

### 10. **RelatorioFinanceiroController** (`app/Modules/Relatorio/Controllers/RelatorioFinanceiroController.php`)
- **Status**: Usa apenas `FinanceiroService`
- **Falta**: Use Cases e Repository DDD
- **Ação**: Criar estrutura DDD completa

---

## ✅ Já Usam DDD (Apenas Verificação)

- ✅ **AssinaturaController** - Usa Use Cases
- ✅ **PlanoController** - Usa Use Cases
- ✅ **UserController** - Usa Use Cases
- ✅ **FixUserRolesController** - Usa Use Cases
- ✅ **TenantController** - Usa Use Cases
- ✅ **FornecedorController** - Usa Use Cases
- ✅ **ContratoController** - Usa Use Cases (parcial)
- ✅ **EmpenhoController** - Usa Use Cases (parcial)
- ✅ **NotaFiscalController** - Usa Use Cases (parcial)
- ✅ **OrcamentoController** - Usa Use Cases (parcial)

---

## 🎯 Prioridade de Refatoração

1. **Alta**: AuthController, PaymentController, WebhookController (acesso direto a modelos)
2. **Média**: CustoIndiretoController, OrgaoController, DocumentoHabilitacaoController
3. **Baixa**: ProcessoController, DashboardController, CalendarioController, RelatorioFinanceiroController

