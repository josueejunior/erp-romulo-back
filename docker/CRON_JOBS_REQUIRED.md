# Cron Jobs Necessários para o Sistema ERP Licitações

## 📋 Cron Jobs Já Implementados e no Crontab

### ✅ 1. Verificar Pagamentos Pendentes
- **Comando**: `pagamentos:verificar-pendentes --horas=1`
- **Frequência**: A cada 2 horas
- **Status**: ✅ Implementado e no crontab
- **Função**: Verifica pagamentos pendentes no Mercado Pago e atualiza assinaturas

### ✅ 2. Verificar Assinaturas Expiradas
- **Comando**: `assinaturas:verificar-expiradas --bloquear`
- **Frequência**: Diariamente às 2h
- **Status**: ✅ Implementado e no crontab
- **Função**: Verifica e processa assinaturas expiradas

### ✅ 3. Verificar Documentos Vencendo
- **Comando**: `documentos:vencimento`
- **Frequência**: Diariamente às 6h
- **Status**: ✅ Implementado e no crontab (também no routes/console.php)
- **Função**: Lista documentos de habilitação vencendo ou vencidos

### ✅ 4. Cleanup de Documentos
- **Comando**: `documentos:cleanup-processos`
- **Frequência**: Diariamente às 3h30
- **Status**: ✅ Implementado e no crontab (também no routes/console.php)
- **Função**: Remove uploads de documentos não referenciados

### ✅ 5. Atualizar Status de Processos
- **Comando**: `processos:atualizar-status-automatico`
- **Frequência**: A cada minuto
- **Status**: ✅ Implementado (está no routes/console.php, mas NÃO no crontab Docker)
- **Função**: Atualiza status de processos (participacao -> julgamento_habilitacao após sessão pública)

---

## ❌ Cron Jobs FALTANDO (Precisam ser Criados)

### 🔴 1. Atualizar Vigência de Contratos e AFs
**Prioridade**: ALTA
- **Comando**: `contratos:atualizar-vigencia` (precisa criar)
- **Frequência**: Diariamente às 1h
- **Função**: 
  - Verificar contratos/AFs com `data_fim_vigencia` passada
  - Atualizar campo `vigente = false`
  - Atualizar situação de AFs baseado em vigência e empenhos
- **Requisito**: Documentação menciona "Atualização automática de vigência"

### 🔴 2. Atualizar Situação de Empenhos
**Prioridade**: ALTA
- **Comando**: `empenhos:atualizar-situacao` (precisa criar)
- **Frequência**: A cada hora (ou a cada 6 horas)
- **Função**:
  - Verificar empenhos com `prazo_entrega_calculado` passado
  - Atualizar `situacao` para "atrasado" se necessário
  - Atualizar para "em_atendimento" se tem NF vinculada
  - Atualizar para "concluido" se todas NFs pagas
- **Requisito**: Documentação menciona "Atualização automática da situação dos prazos"

### 🔴 3. Recalcular Saldos (Fallback)
**Prioridade**: MÉDIA
- **Comando**: `saldos:recalcular` (precisa criar)
- **Frequência**: Diariamente às 3h
- **Função**:
  - Recalcular saldos de processos em execução
  - Recalcular saldos de contratos ativos
  - Recalcular saldos de AFs ativas
  - Recalcular saldos de empenhos
- **Requisito**: Garantir consistência dos saldos (observers já fazem, mas é bom ter fallback)

### 🟡 4. Notificar Calendário de Disputas
**Prioridade**: MÉDIA
- **Comando**: `processos:notificar-disputas` (precisa criar)
- **Frequência**: Diariamente às 8h
- **Função**:
  - Buscar processos com sessão pública nos próximos 3 dias
  - Notificar usuários responsáveis (se houver sistema de notificações)
  - Log de processos que precisam atenção
- **Requisito**: Documentação menciona "calendário de disputas com avisos"

### 🟡 5. Notificar Calendário de Julgamento
**Prioridade**: MÉDIA
- **Comando**: `processos:notificar-julgamento` (precisa criar)
- **Frequência**: Diariamente às 9h
- **Função**:
  - Buscar processos em julgamento com lembretes próximos
  - Buscar processos em julgamento há mais de 7 dias sem atualização
  - Notificar usuários responsáveis
- **Requisito**: Documentação menciona "calendário de julgamento" e "lembretes"

### 🟡 6. Atualizar Situação de AFs
**Prioridade**: MÉDIA (pode ser combinado com item 1)
- **Comando**: `afs:atualizar-situacao` (precisa criar, ou incluir no comando de contratos)
- **Frequência**: Diariamente às 1h30
- **Função**:
  - Atualizar situação de AFs: "Aguardando empenho", "Atendendo", "Concluída"
  - Baseado em empenhos vinculados
- **Requisito**: Documentação menciona "Atualização automática da situação da AF"

### 🟢 7. Alertar Empenhos com Prazo Próximo
**Prioridade**: BAIXA (nice to have)
- **Comando**: `empenhos:alertar-prazos` (precisa criar)
- **Frequência**: Diariamente às 10h
- **Função**:
  - Buscar empenhos com prazo de entrega nos próximos 7 dias
  - Notificar responsáveis
- **Requisito**: Melhoria de UX

---

## 📝 Resumo

### Total de Cron Jobs Necessários: 11

**Já Implementados**: 5
- ✅ 4 no crontab Docker
- ✅ 1 no routes/console.php (mas não no Docker crontab)

**Precisam ser Criados**: 6
- 🔴 ALTA prioridade: 2 (Vigência de Contratos/AFs, Situação de Empenhos)
- 🟡 MÉDIA prioridade: 3 (Recalcular Saldos, Notificar Disputas, Notificar Julgamento, Atualizar AFs)
- 🟢 BAIXA prioridade: 1 (Alertar Prazos de Empenhos)

---

## 🔧 Ações Necessárias

1. **Adicionar comando existente ao crontab Docker**:
   - `processos:atualizar-status-automatico` (já existe, só falta adicionar no crontab)

2. **Criar novos comandos**:
   - `contratos:atualizar-vigencia`
   - `empenhos:atualizar-situacao`
   - `saldos:recalcular`
   - `processos:notificar-disputas`
   - `processos:notificar-julgamento`
   - `empenhos:alertar-prazos`

3. **Atualizar crontab Docker** com todos os comandos

---

## 📊 Cronograma Sugerido

```
00:00 - (vazio - horário de baixo uso)
01:00 - contratos:atualizar-vigencia
01:30 - afs:atualizar-situacao (ou incluir no anterior)
02:00 - assinaturas:verificar-expiradas --bloquear
03:00 - saldos:recalcular
03:30 - documentos:cleanup-processos
06:00 - documentos:vencimento
08:00 - processos:notificar-disputas
09:00 - processos:notificar-julgamento
10:00 - empenhos:alertar-prazos
12:00 - pagamentos:verificar-pendentes (a cada 2h - 00, 02, 04, 06, 08, 10, 12, 14, 16, 18, 20, 22)
* * * * * - processos:atualizar-status-automatico (a cada minuto - mas pode ser a cada 5 min)
```

---

## ⚠️ Observações

1. **Processos**: O comando `processos:atualizar-status-automatico` executa a cada minuto via `routes/console.php`. 
   - Para Docker, pode ser mantido no routes/console.php (Laravel Scheduler)
   - Ou movido para crontab a cada 5 minutos (menos carga)

2. **Notificações**: Se não houver sistema de email/notificações implementado, os comandos de notificação podem apenas registrar em log.

3. **Saldos**: Os Observers já atualizam saldos quando há mudanças. O comando de recalcular é apenas um fallback para garantir consistência.

