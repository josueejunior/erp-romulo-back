# 💳 Proposta: Sistema de Assinaturas e Controle de Acesso

## 📋 Objetivo

Implementar sistema de assinaturas/planos para controlar acesso dos usuários e bloquear funcionalidades quando a assinatura estiver vencida ou inativa.

## 🏗️ Estrutura Proposta

### 1. Tabelas Necessárias

#### `planos` (tabela central - não tenant)
```sql
- id
- nome (ex: "Básico", "Profissional", "Enterprise")
- descricao
- preco_mensal (decimal)
- preco_anual (decimal)
- limite_processos (int, nullable) - null = ilimitado
- limite_usuarios (int, nullable) - null = ilimitado
- limite_armazenamento_mb (int, nullable) - null = ilimitado
- recursos_disponiveis (json) - lista de funcionalidades
- ativo (boolean)
- created_at, updated_at
```

#### `assinaturas` (tabela central - não tenant)
```sql
- id
- tenant_id (foreign key)
- plano_id (foreign key)
- status (enum: 'ativa', 'cancelada', 'suspensa', 'expirada')
- data_inicio (date)
- data_fim (date)
- data_cancelamento (date, nullable)
- valor_pago (decimal)
- metodo_pagamento (string, nullable)
- created_at, updated_at
```

#### Adicionar em `tenants` (tabela central)
```sql
- plano_atual_id (foreign key, nullable)
- assinatura_atual_id (foreign key, nullable)
- limite_processos (int, nullable) - cache do plano
- limite_usuarios (int, nullable) - cache do plano
```

### 2. Models

#### `Plano.php` (central)
```php
- Relacionamento: hasMany(Assinatura::class)
- Métodos: isAtivo(), getRecursosDisponiveis()
```

#### `Assinatura.php` (central)
```php
- Relacionamento: belongsTo(Tenant::class), belongsTo(Plano::class)
- Métodos: isAtiva(), isExpirada(), diasRestantes(), renovar(), cancelar()
```

#### Atualizar `Tenant.php`
```php
- Relacionamento: belongsTo(Plano::class), hasMany(Assinatura::class)
- Métodos: temAssinaturaAtiva(), podeCriarProcesso(), podeAdicionarUsuario()
```

### 3. Middleware

#### `CheckSubscription.php`
```php
- Verifica se tenant tem assinatura ativa
- Verifica se assinatura não expirou
- Bloqueia acesso se assinatura inválida
- Retorna erro 403 com mensagem amigável
```

### 4. Service

#### `SubscriptionService.php`
```php
- criarAssinatura($tenantId, $planoId, $periodo)
- renovarAssinatura($assinaturaId)
- cancelarAssinatura($assinaturaId)
- verificarLimites($tenantId) - verifica limites de processos/usuários
- bloquearAcessoSeNecessario($tenantId)
```

### 5. Controllers

#### `PlanoController.php` (API)
- `index()` - Listar planos disponíveis
- `show($id)` - Detalhes do plano

#### `AssinaturaController.php` (API)
- `index()` - Listar assinaturas do tenant
- `store()` - Criar nova assinatura
- `show($id)` - Detalhes da assinatura
- `renovar($id)` - Renovar assinatura
- `cancelar($id)` - Cancelar assinatura

### 6. Validações

#### No `ProcessoController`
```php
- Antes de criar processo: verificar limite de processos
- Se exceder limite: retornar erro 403 com mensagem
```

#### No `UserController`
```php
- Antes de criar usuário: verificar limite de usuários
- Se exceder limite: retornar erro 403 com mensagem
```

## 🔒 Fluxo de Bloqueio

### 1. Login
```
Usuário faz login → 
Middleware verifica assinatura → 
Se expirada/suspensa: bloqueia acesso com mensagem
```

### 2. Criar Processo
```
Usuário tenta criar processo → 
Verifica assinatura ativa → 
Verifica limite de processos → 
Se OK: permite criação
Se não: retorna erro 403
```

### 3. Adicionar Usuário
```
Admin tenta adicionar usuário → 
Verifica assinatura ativa → 
Verifica limite de usuários → 
Se OK: permite criação
Se não: retorna erro 403
```

## 📊 Dashboard de Assinatura

### Informações a exibir:
- Status da assinatura (Ativa/Expirada/Suspensa)
- Plano atual
- Data de vencimento
- Dias restantes
- Limites utilizados (processos, usuários)
- Botão para renovar/upgrade

## 🚨 Mensagens de Erro

### Assinatura Expirada
```json
{
  "message": "Sua assinatura expirou em 15/12/2025. Renove sua assinatura para continuar usando o sistema.",
  "code": "SUBSCRIPTION_EXPIRED",
  "data_vencimento": "2025-12-15",
  "dias_expirado": 3
}
```

### Limite de Processos
```json
{
  "message": "Você atingiu o limite de processos do seu plano (50 processos). Faça upgrade para criar mais processos.",
  "code": "PROCESS_LIMIT_REACHED",
  "limite": 50,
  "utilizado": 50
}
```

### Limite de Usuários
```json
{
  "message": "Você atingiu o limite de usuários do seu plano (10 usuários). Faça upgrade para adicionar mais usuários.",
  "code": "USER_LIMIT_REACHED",
  "limite": 10,
  "utilizado": 10
}
```

## 📝 Implementação Sugerida

### Fase 1: Estrutura Base
1. ✅ Criar migrations (planos, assinaturas)
2. ✅ Criar models (Plano, Assinatura)
3. ✅ Atualizar model Tenant
4. ✅ Criar seeder com planos padrão

### Fase 2: Middleware e Validações
1. ✅ Criar middleware CheckSubscription
2. ✅ Aplicar middleware nas rotas críticas
3. ✅ Adicionar validações nos controllers

### Fase 3: Interface
1. ✅ Criar controllers de API
2. ✅ Criar tela de planos no frontend
3. ✅ Criar dashboard de assinatura
4. ✅ Adicionar notificações de vencimento

### Fase 4: Integração Pagamento (Futuro)
1. ⏳ Integração com gateway de pagamento
2. ⏳ Webhooks de confirmação
3. ⏳ Renovação automática

## 🎯 Planos Sugeridos

### Plano Básico
- R$ 99/mês
- 10 processos ativos
- 3 usuários
- 1GB armazenamento

### Plano Profissional
- R$ 299/mês
- 50 processos ativos
- 10 usuários
- 10GB armazenamento

### Plano Enterprise
- R$ 799/mês
- Processos ilimitados
- Usuários ilimitados
- Armazenamento ilimitado
- Suporte prioritário

## ⚠️ Considerações

1. **Grace Period**: Permitir acesso por X dias após vencimento (ex: 7 dias)
2. **Downgrade**: O que fazer com dados que excedem o novo plano?
3. **Backup**: Manter dados mesmo após cancelamento (soft delete)
4. **Notificações**: Avisar antes do vencimento (7, 3, 1 dia antes)

