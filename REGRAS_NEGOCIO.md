# Regras de Negócio - Sistema de Gestão de Licitações

## 📘 ESTRUTURA INICIAL – RÔMULO PELICIER

### EMPRESA (LICITANTE)

- Empresa não cria o certame, apenas participa
- Uma empresa pode participar de vários processos simultaneamente
- Empresa pode ter: vários usuários, vários documentos de habilitação, vários custos indiretos
- Empresas não são excluídas, apenas inativadas (soft delete)
- Todo histórico financeiro e jurídico permanece vinculado à empresa

### USUÁRIOS E PERMISSÕES

#### Perfis:
- **Administrador**: Todas as permissões
- **Operacional**: Criar processos, converter prospecto em execução, marcar vencido/perdido
- **Financeiro**: Gerenciar custos, confirmar pagamentos
- **Consulta**: Apenas visualização

#### Regras:
- Apenas **Administrador** e **Operacional** podem:
  - criar processos
  - converter prospecto em execução
  - marcar processo como vencido ou perdido

- Apenas **Administrador** e **Financeiro** podem:
  - gerenciar custos
  - confirmar pagamentos

- Usuário **Consulta** apenas visualiza dados

### ÓRGÃO (CONTRATANTE)

- UASG não é obrigatória
- Quando não houver UASG, usa-se o nome do órgão

### SETOR / UNIDADE DO ÓRGÃO

- Um processo deve estar vinculado a um único setor
- Um órgão pode ter vários setores

### LISTA DE HABILITAÇÃO

- Documentos possuem data de emissão e validade
- Sistema deve alertar vencimentos
- No processo, o usuário escolhe quais documentos serão exigidos
- Documentos vencidos não são bloqueados automaticamente, apenas sinalizados

### FORNECEDOR / TRANSPORTADORA

- Um item pode ter vários fornecedores cotados
- Um fornecedor pode fornecer vários itens
- Transportadora pode ser vinculada ao fornecedor ou escolhida separadamente

### PROCESSO (CERTAME)

#### Identificação:
- Número da Dispensa/Pregão + UASG ou Nome do Órgão
- Quando não houver UASG, usa-se o nome do órgão

#### Status Inicial:
- Todo processo inicia com status: **PARTICIPAÇÃO**

#### Status do Processo:
- `participacao` - Fase pré-disputa (inicial)
- `julgamento_habilitacao` - Após disputa, em julgamento
- `vencido` - Marcado como vencido (sempre manual)
- `perdido` - Todos os itens desclassificados/inabilitados
- `execucao` - Processo vencido, em execução
- `arquivado` - Processo perdido, arquivado

#### Regras de Transição:
- Após data/hora da sessão: sistema sugere status `julgamento_habilitacao` (confirmação manual)
- Se todos os itens forem desclassificados/inabilitados:
  - Sistema sugere `perdido`
  - Se confirmado → `arquivado`
- Se houver ao menos um item aceito: permanece em julgamento
- `vencido` é sempre manual
- Ao marcar como `vencido`, o processo entra em `execucao`
- Em `execucao`, os dados do certame ficam travados (não podem ser editados)

### ITENS DO PROCESSO

#### Status por Item:
- `pendente` - Item aguardando
- `aceito` - Item aceito
- `aceito_habilitado` - Item aceito e habilitado
- `desclassificado` - Item desclassificado
- `inabilitado` - Item inabilitado

### ORÇAMENTOS (COTAÇÕES)

- Cada item pode ter vários orçamentos
- Antes da disputa: um orçamento deve ser marcado como `fornecedor_escolhido`
- A especificação técnica pode ser ajustada
- Marca/modelo podem variar por fornecedor

### FORMAÇÃO DE PREÇOS

Fórmula de cálculo:
```
Base = Custo Produto + Frete
Impostos = Base * (percentual_impostos / 100)
Subtotal = Base + Impostos
Margem = Subtotal * (percentual_margem / 100)
Preço Mínimo = Subtotal + Margem
Preço Recomendado = Preço Mínimo * 1.10 (10% a mais)
```

- O preço mínimo deve ser exibido no calendário de disputas

### CALENDÁRIO DE DISPUTAS

- Exibe data e hora da sessão
- Processo
- Empresa
- Preço mínimo de venda por item (visual)

### DISPUTA

Após a sessão pública:
- Registrar valor final
- Registrar classificação
- Incluir observações

Após data/hora da sessão:
- Sistema sugere status `julgamento_habilitacao`
- Confirmação sempre manual

### JULGAMENTO E HABILITAÇÃO

Para cada item:
- Informar classificação
- Indicar chance de arremate
- Criar lembretes
- Registrar valor negociado pós-disputa (sem apagar o anterior)

### EXECUÇÃO

- Ao marcar como `vencido`, o processo entra em `execucao`
- A partir daqui, os dados do certame ficam travados

### CONTRATO

Regras:
- Um processo pode ter: nenhum, um ou vários contratos
- Contrato passa a ser `vigente` no momento da inclusão
- Contrato não impede existência de AF e/ou empenhos diretos
- Saldo do contrato é atualizado conforme empenhos vinculados

### AUTORIZAÇÃO DE FORNECIMENTO (AF)

Regras:
- Um processo pode ter: nenhuma, uma ou várias AFs
- AF não substitui contrato
- AF pode gerar empenhos

Situação:
- `aguardando_empenho` - Aguardando empenho
- `atendendo` - Atendendo
- `concluida` - Concluída

### EMPENHOS

Regras:
- Empenho pode estar vinculado a: contrato, AF ou diretamente ao processo
- Um processo pode ter vários empenhos
- Empenho é `concluido` quando o usuário informa a entrega do material

Efeitos:
- Atualiza saldo do contrato (se houver)
- Atualiza saldo da AF (se houver)
- Atualiza saldo do processo (sempre)

### NOTAS FISCAIS

#### Entrada (Documentos de Custo):
- Registrar custos reais: NF-e, recibos, RPAs, comprovantes
- Custos anteriores permanecem para histórico e comparação

#### Saída:
- Registrar faturamento, logística, entrega

### SALDOS E CONTROLE FINANCEIRO

O sistema deve controlar:
- Custos diretos por processo
- Custos indiretos por empresa
- Saldo financeiro do processo
- Saldo de contratos, AFs e empenhos

Relatórios:
- Lucro por processo
- Lucro por período
- Margem real
- Saldo a receber

### NOTA FINAL

O processo passa por diversas fases e status e, ao alcançar a fase de EXECUÇÃO, poderá ser vinculado simultaneamente ou não a:
- um ou mais contratos
- uma ou mais AFs
- um ou mais empenhos

O sistema deve:
- vincular tudo ao saldo do processo
- permitir descontos progressivos conforme execução
- manter histórico completo e imutável

