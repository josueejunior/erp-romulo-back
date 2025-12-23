# 📋 Plano de Organização de Módulos

## 🎯 Objetivo

Organizar o código em módulos funcionais seguindo a arquitetura descrita, facilitando manutenção, testabilidade e escalabilidade.

## 📁 Estrutura Proposta

```
app/
├── Modules/                    # Módulos funcionais
│   ├── Auth/
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   └── AdminUser.php
│   │   ├── Services/
│   │   │   └── AuthIdentityService.php
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   └── UserController.php
│   │   └── Resources/
│   │
│   ├── Empresa/
│   │   ├── Models/
│   │   │   └── Empresa.php
│   │   ├── Services/
│   │   │   └── TenantService.php
│   │   ├── Controllers/
│   │   │   ├── TenantController.php
│   │   │   ├── EmpresaController.php
│   │   │   └── EmpresaSelecaoController.php
│   │   └── Resources/
│   │
│   ├── Processo/
│   │   ├── Models/
│   │   │   ├── Processo.php
│   │   │   ├── ProcessoItem.php
│   │   │   ├── ProcessoDocumento.php
│   │   │   └── ProcessoItemVinculo.php
│   │   ├── Services/
│   │   │   ├── ProcessoStatusService.php
│   │   │   ├── ProcessoValidationService.php
│   │   │   ├── SaldoService.php
│   │   │   ├── DisputaService.php
│   │   │   └── ExportacaoService.php
│   │   ├── Controllers/
│   │   │   ├── ProcessoController.php
│   │   │   ├── ProcessoItemController.php
│   │   │   ├── DisputaController.php
│   │   │   ├── JulgamentoController.php
│   │   │   ├── SaldoController.php
│   │   │   └── ExportacaoController.php
│   │   ├── Resources/
│   │   │   ├── ProcessoResource.php
│   │   │   ├── ProcessoListResource.php
│   │   │   └── ProcessoItemResource.php
│   │   ├── Observers/
│   │   │   └── ProcessoObserver.php
│   │   └── Policies/
│   │       └── ProcessoPolicy.php
│   │
│   ├── Orcamento/
│   │   ├── Models/
│   │   │   ├── Orcamento.php
│   │   │   ├── OrcamentoItem.php
│   │   │   └── FormacaoPreco.php
│   │   ├── Services/
│   │   │   └── FormacaoPrecoService.php
│   │   ├── Controllers/
│   │   │   ├── OrcamentoController.php
│   │   │   └── FormacaoPrecoController.php
│   │   ├── Resources/
│   │   │   └── OrcamentoResource.php
│   │   └── Policies/
│   │       └── OrcamentoPolicy.php
│   │
│   ├── Contrato/
│   │   ├── Models/
│   │   │   └── Contrato.php
│   │   ├── Controllers/
│   │   │   └── ContratoController.php
│   │   ├── Observers/
│   │   │   └── ContratoObserver.php
│   │   └── Policies/
│   │       └── ContratoPolicy.php
│   │
│   ├── Fornecedor/
│   │   ├── Models/
│   │   │   ├── Fornecedor.php
│   │   │   └── Transportadora.php
│   │   ├── Controllers/
│   │   │   └── FornecedorController.php
│   │   └── Resources/
│   │       └── FornecedorResource.php
│   │
│   ├── Orgao/
│   │   ├── Models/
│   │   │   ├── Orgao.php
│   │   │   └── Setor.php
│   │   ├── Controllers/
│   │   │   ├── OrgaoController.php
│   │   │   └── SetorController.php
│   │   └── Resources/
│   │       ├── OrgaoResource.php
│   │       └── SetorResource.php
│   │
│   ├── Documento/
│   │   ├── Models/
│   │   │   └── DocumentoHabilitacao.php
│   │   └── Controllers/
│   │       └── DocumentoHabilitacaoController.php
│   │
│   ├── Empenho/
│   │   ├── Models/
│   │   │   └── Empenho.php
│   │   ├── Controllers/
│   │   │   └── EmpenhoController.php
│   │   └── Observers/
│   │       └── EmpenhoObserver.php
│   │
│   ├── NotaFiscal/
│   │   ├── Models/
│   │   │   └── NotaFiscal.php
│   │   ├── Controllers/
│   │   │   └── NotaFiscalController.php
│   │   ├── Resources/
│   │   │   └── NotaFiscalResource.php
│   │   └── Observers/
│   │       └── NotaFiscalObserver.php
│   │
│   ├── AutorizacaoFornecimento/
│   │   ├── Models/
│   │   │   └── AutorizacaoFornecimento.php
│   │   └── Controllers/
│   │       └── AutorizacaoFornecimentoController.php
│   │
│   ├── Custo/
│   │   ├── Models/
│   │   │   └── CustoIndireto.php
│   │   └── Controllers/
│   │       └── CustoIndiretoController.php
│   │
│   ├── Auditoria/
│   │   ├── Models/
│   │   │   ├── AuditLog.php
│   │   │   └── AuditoriaLog.php
│   │   └── Observers/
│   │       └── AuditObserver.php
│   │
│   ├── Assinatura/
│   │   ├── Models/
│   │   │   ├── Plano.php
│   │   │   └── Assinatura.php
│   │   ├── Controllers/
│   │   │   ├── PlanoController.php
│   │   │   └── AssinaturaController.php
│   │   └── Services/
│   │
│   └── Calendario/
│       ├── Services/
│       │   └── CalendarioService.php
│       └── Controllers/
│           ├── CalendarioController.php
│           └── CalendarioDisputasController.php
│
├── Shared/                     # Código compartilhado
│   ├── Contracts/
│   ├── Database/
│   ├── Helpers/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── BaseApiController.php
│   │   │   ├── BaseServiceController.php
│   │   │   └── RoutingController.php
│   │   ├── Middleware/
│   │   └── Resources/
│   ├── Services/
│   │   ├── RedisService.php
│   │   └── FinanceiroService.php
│   └── Rules/
│
└── Admin/                      # Módulo Admin
    ├── Controllers/
    │   ├── AdminAuthController.php
    │   ├── AdminTenantController.php
    │   └── AdminUserController.php
    └── Middleware/
        └── IsSuperAdmin.php
```

## 📊 Mapeamento Atual → Novo

| Atual | Novo |
|-------|------|
| `app/Models/Processo.php` | `app/Modules/Processo/Models/Processo.php` |
| `app/Services/ProcessoStatusService.php` | `app/Modules/Processo/Services/ProcessoStatusService.php` |
| `app/Http/Controllers/Api/ProcessoController.php` | `app/Modules/Processo/Controllers/ProcessoController.php` |
| `app/Http/Resources/ProcessoResource.php` | `app/Modules/Processo/Resources/ProcessoResource.php` |
| `app/Observers/ProcessoObserver.php` | `app/Modules/Processo/Observers/ProcessoObserver.php` |
| `app/Policies/ProcessoPolicy.php` | `app/Modules/Processo/Policies/ProcessoPolicy.php` |

## 🚀 Fases de Implementação

### Fase 1: Estrutura Base
- [ ] Criar estrutura de diretórios
- [ ] Criar classes base compartilhadas
- [ ] Configurar autoloading

### Fase 2: Módulo Processo (Piloto)
- [ ] Mover Models
- [ ] Mover Services
- [ ] Mover Controllers
- [ ] Mover Resources
- [ ] Mover Observers
- [ ] Mover Policies
- [ ] Atualizar namespaces
- [ ] Atualizar imports
- [ ] Testar

### Fase 3: Outros Módulos
- [ ] Módulo Orcamento
- [ ] Módulo Contrato
- [ ] Módulo Fornecedor
- [ ] Módulo Orgao
- [ ] Módulo Empenho
- [ ] Módulo NotaFiscal
- [ ] Módulo AutorizacaoFornecimento
- [ ] Módulo Custo
- [ ] Módulo Documento
- [ ] Módulo Auditoria
- [ ] Módulo Assinatura
- [ ] Módulo Auth
- [ ] Módulo Empresa
- [ ] Módulo Calendario

### Fase 4: Shared e Admin
- [ ] Organizar código compartilhado
- [ ] Organizar módulo Admin
- [ ] Atualizar rotas
- [ ] Atualizar service providers

### Fase 5: Limpeza
- [ ] Remover diretórios antigos vazios
- [ ] Atualizar documentação
- [ ] Testes finais

## ⚠️ Considerações

1. **Namespaces**: Atualizar todos os namespaces
2. **Imports**: Atualizar todos os `use` statements
3. **Rotas**: Atualizar referências nos arquivos de rotas
4. **Service Providers**: Atualizar registros de observers, policies, etc.
5. **Composer**: Atualizar autoload se necessário
6. **Testes**: Garantir que todos os testes continuem funcionando


