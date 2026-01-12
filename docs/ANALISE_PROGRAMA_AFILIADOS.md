# 📊 Análise: Programa de Afiliados ADDSIMP

## ✅ O QUE JÁ ESTÁ IMPLEMENTADO

### 1. Rastreamento de Referência (Link do Afiliado)
- ✅ Sistema rastreia `?ref=codigo` na URL
- ✅ Armazena em `afiliado_referencias` (session_id, IP, email, CNPJ)
- ✅ Vincula referência ao tenant quando cadastro é concluído
- ✅ Valida se CNPJ já usou cupom (uso único)

### 2. Sistema de Cupom
- ✅ Validação de cupom de afiliado
- ✅ Cálculo de desconto (percentual configurável)
- ✅ Cupom vinculado ao afiliado
- ✅ Uso único por CNPJ

### 3. Onboarding Obrigatório
- ✅ Sistema de onboarding com `onboarding_progress`
- ✅ Bloqueio de acesso a planos até concluir tutorial
- ✅ Middleware `CheckOnboarding` protege rota `/planos`
- ✅ Frontend tem `OnboardingGuard` e `OnboardingContext`

### 4. Registro de Afiliado na Empresa
- ✅ Quando empresa se cadastra com cupom, afiliado é registrado
- ✅ Campos `afiliado_id`, `afiliado_codigo`, `afiliado_desconto_aplicado` na tabela `empresas`
- ✅ `RegistrarAfiliadoNaEmpresaUseCase` faz o registro

### 5. Estrutura de Comissões
- ✅ Tabela `afiliado_indicacoes` existe
- ✅ Campos para comissão: `comissao_percentual`, `valor_comissao`, `comissao_paga`
- ✅ Model `AfiliadoIndicacao` com métodos auxiliares

---

## ❌ O QUE FALTA IMPLEMENTAR

### 🔴 CRÍTICO - Sistema de Comissão Recorrente

#### 1. Criação de Indicação ao Contratar
**Status:** ❌ NÃO IMPLEMENTADO

**O que falta:**
- Quando empresa contrata com cupom de afiliado, criar registro em `afiliado_indicacoes`
- Preencher: `afiliado_id`, `tenant_id`, `empresa_id`, `plano_id`, `valor_comissao`, etc.

**Onde implementar:**
- `CadastrarEmpresaPublicamenteUseCase::processarPagamentoECriarAssinatura()`
- `ProcessarAssinaturaPlanoUseCase::criarAssinatura()`
- Listener para evento `AssinaturaCriada`

#### 2. Geração Automática de Comissão Recorrente
**Status:** ❌ NÃO IMPLEMENTADO

**O que falta:**
- Job/Command que roda periodicamente (ex: diariamente)
- Verifica assinaturas ativas vinculadas a afiliados
- Calcula comissão baseada em:
  - Valor efetivamente pago (não apenas faturado)
  - Percentual de comissão do afiliado
  - Desconto aplicado
- Cria registros de comissão recorrente (pode ser nova tabela ou campo em `afiliado_indicacoes`)

**Fórmula:**
```
Comissão = (Valor Pago - Desconto) × (Percentual Comissão / 100)
```

**Exemplo:**
- Plano: R$ 100,00
- Desconto 30%: R$ 70,00 pago
- Comissão 20%: R$ 14,00 por ciclo

#### 3. Cálculo Baseado em Pagamento Confirmado
**Status:** ❌ NÃO IMPLEMENTADO

**O que falta:**
- Listener para evento de pagamento aprovado
- Verificar se assinatura tem afiliado vinculado
- Calcular e registrar comissão apenas quando pagamento é confirmado
- Não gerar comissão para pagamentos pendentes ou rejeitados

**Onde implementar:**
- Listener para `AssinaturaAtualizada` (quando status muda para 'ativa' após pagamento)
- `VerificarPagamentoPendenteJob` (quando PIX é aprovado)
- Webhook de pagamento do Mercado Pago

#### 4. Ajuste de Comissão em Upgrade/Downgrade
**Status:** ❌ NÃO IMPLEMENTADO

**O que falta:**
- Quando cliente faz upgrade/downgrade, ajustar comissão
- Comissão passa a incidir sobre novo valor
- Registrar histórico de mudanças

**Onde implementar:**
- `TrocarPlanoAssinaturaUseCase::executar()`
- Listener para evento de troca de plano

#### 5. Parar Comissão em Cancelamento/Inadimplência
**Status:** ❌ NÃO IMPLEMENTADO

**O que falta:**
- Quando assinatura é cancelada, parar de gerar comissão
- Quando assinatura expira ou fica inadimplente, parar comissão
- Atualizar status em `afiliado_indicacoes`

**Onde implementar:**
- `VerificarAssinaturasExpiradas` command
- Listener para `AssinaturaAtualizada` (status = 'cancelada' ou 'expirada')

#### 6. Sistema de Pagamento de Comissões
**Status:** ❌ NÃO IMPLEMENTADO

**O que falta:**
- Tabela para registrar pagamentos de comissões aos afiliados
- Relatório de comissões pendentes/pagas
- Período de competência (ex: pagar no mês seguinte ao faturamento)
- Interface admin para marcar comissões como pagas

**Estrutura sugerida:**
```sql
CREATE TABLE afiliado_comissoes_pagamentos (
    id BIGINT PRIMARY KEY,
    afiliado_id BIGINT,
    periodo_competencia DATE, -- Mês/ano da comissão
    valor_total DECIMAL(10,2),
    status ENUM('pendente', 'pago', 'cancelado'),
    data_pagamento DATE,
    observacoes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 7. Exibição Automática de Cupom Após Onboarding
**Status:** ⚠️ PARCIALMENTE IMPLEMENTADO

**O que falta:**
- Quando usuário acessa `/planos` após concluir onboarding, verificar se veio por link de afiliado
- Se sim, exibir cupom automaticamente na tela de planos
- Não exigir que usuário digite o cupom manualmente

**Onde implementar:**
- Frontend: `Planos.jsx` - verificar se tem referência de afiliado ativa
- Backend: Endpoint para buscar cupom automático baseado em referência

#### 8. Relatório de Comissões para Afiliados
**Status:** ❌ NÃO IMPLEMENTADO

**O que falta:**
- Dashboard para afiliados verem:
  - Total de indicações
  - Comissões geradas
  - Comissões pagas/pendentes
  - Histórico de pagamentos
  - Clientes ativos/inativos

**Onde implementar:**
- Frontend: Página `/afiliado/dashboard` ou `/afiliado/comissoes`
- Backend: Endpoints para buscar dados de comissões do afiliado

---

## 📋 PLANO DE IMPLEMENTAÇÃO SUGERIDO

### Fase 1: Comissão na Contratação (Prioridade ALTA)
1. Criar `CriarIndicacaoAfiliadoUseCase`
2. Chamar após criar assinatura com cupom
3. Registrar em `afiliado_indicacoes`

### Fase 2: Comissão Recorrente (Prioridade ALTA)
1. Criar tabela `afiliado_comissoes_recorrentes` ou adicionar campos em `afiliado_indicacoes`
2. Criar Command `afiliados:calcular-comissoes` (roda diariamente)
3. Calcular comissão baseada em pagamentos confirmados
4. Criar registros de comissão para cada ciclo de 30 dias

### Fase 3: Ajustes e Controles (Prioridade MÉDIA)
1. Ajustar comissão em upgrade/downgrade
2. Parar comissão em cancelamento/inadimplência
3. Sistema de pagamento de comissões

### Fase 4: Interface e Relatórios (Prioridade BAIXA)
1. Dashboard de comissões para afiliados
2. Interface admin para gerenciar pagamentos
3. Relatórios e exportações

---

## 🔍 PONTOS DE ATENÇÃO

1. **Valor Base da Comissão:** Deve ser sempre o valor EFETIVAMENTE PAGO, não o valor original do plano
2. **Ciclo de 30 dias:** Comissão deve ser gerada a cada ciclo de 30 dias, não mensalmente
3. **Pagamento Confirmado:** Só gerar comissão quando pagamento for confirmado (status = 'aprovado')
4. **Uso Único do Cupom:** Já implementado, mas validar em todos os pontos
5. **Vínculo Permanente:** Cliente fica vinculado ao afiliado mesmo após cancelamento/reativação

---

## 📝 NOTAS TÉCNICAS

- A tabela `afiliado_indicacoes` parece ser para a primeira indicação/contratação
- Pode ser necessário criar uma nova tabela para comissões recorrentes ou adicionar campos
- Considerar criar eventos de domínio: `ComissaoGerada`, `ComissaoPaga`, etc.
- Usar Jobs para processar comissões em background (evitar timeout)




