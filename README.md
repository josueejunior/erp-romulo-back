# ERP de Licitações

Sistema completo de gestão de licitações desenvolvido em Laravel 12.

## Visão Geral

Este sistema permite que empresas gerenciem todo o ciclo de vida de participação em licitações, desde a preparação até a execução dos contratos. O coração do sistema é um **Processo (Certame)** que evolui através de diferentes estágios:

1. **Participação**: Preparação inicial com documentos, fornecedores e orçamentos
2. **Julgamento e Habilitação**: Controle fino por item com status, chances e lembretes
3. **Execução**: Após vencer, o processo fica travado e vira módulo financeiro/entrega

## Funcionalidades Principais

### 1. Multi-Empresa
- Usuários podem ter acesso a múltiplas empresas
- Seleção de empresa ativa que filtra todos os dados
- Permissões por empresa (Admin, Operacional, Financeiro, Consulta)

### 2. Gestão de Processos
- Criação de processos (Dispensa/Pregão)
- Gestão de itens com especificações técnicas
- Controle de documentos de habilitação
- Orçamentos por item com múltiplos fornecedores
- Formação de preços com cálculo de impostos e margem
- Calendário de disputas com indicadores visuais

### 3. Disputa e Julgamento
- Registro de resultados da sessão pública
- Status por item (Aceito, Desclassificado, Inabilitado)
- Controle de chances de arremate
- Lembretes e negociações pós-disputa

### 4. Execução
- Processos vencidos ficam travados (imutáveis)
- Gestão de contratos com controle de saldo
- Autorizações de Fornecimento (AFs)
- Empenhos vinculados a contratos/AFs
- Notas fiscais de entrada e saída
- Controle financeiro completo

### 5. Relatórios Financeiros
- Saldo por processo
- Lucro por processo e período
- Margem real vs estimada
- Saldo a receber

## Instalação

1. Clone o repositório
2. Instale as dependências:
```bash
composer install
npm install
```

3. Configure o arquivo `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

4. Configure o banco de dados no `.env` e execute as migrations:
```bash
php artisan migrate
```

5. Execute os seeders para criar dados iniciais:
```bash
php artisan db:seed
```

6. Inicie o servidor:
```bash
php artisan serve
npm run dev
```

## Credenciais Padrão

Após executar o seeder:
- **Email**: admin@exemplo.com
- **Senha**: password

## Estrutura do Banco de Dados

### Tabelas Principais

- **empresas**: Dados das empresas participantes
- **orgaos**: Órgãos contratantes
- **setors**: Setores dos órgãos
- **processos**: Processos licitatórios (certames)
- **processo_itens**: Itens de cada processo
- **fornecedores**: Fornecedores cadastrados
- **orcamentos**: Cotações por item
- **formacao_precos**: Cálculo de preços
- **documentos_habilitacao**: Biblioteca de documentos
- **contratos**: Contratos de execução
- **autorizacoes_fornecimento**: AFs
- **empenhos**: Empenhos vinculados
- **notas_fiscais**: NFs de entrada e saída
- **custos_indiretos**: Custos da empresa
- **auditoria_logs**: Logs de auditoria

## Fluxo de Uso

### 1. Preparação Base
- Cadastrar empresa, órgãos, setores
- Cadastrar fornecedores e transportadoras
- Cadastrar documentos de habilitação

### 2. Criar Processo
- Preencher dados do certame
- Adicionar itens com especificações
- Importar documentos exigidos
- Criar orçamentos por item
- Calcular formação de preços

### 3. Calendário de Disputas
- Visualizar próximas sessões
- Verificar preços mínimos
- Verificar status de documentos

### 4. Pós-Disputa
- Registrar valores finais
- Atualizar classificação por item
- Marcar status (Vencido/Perdido)

### 5. Execução
- Criar contratos
- Gerar AFs
- Registrar empenhos
- Lançar notas fiscais
- Acompanhar saldos e lucros

## Regras Importantes

1. **Empresa não cria certame**: A empresa participa, o certame é do órgão
2. **Execução travada**: Processos em execução não podem ter dados do certame alterados
3. **Histórico preservado**: Empresas nunca são excluídas, apenas inativadas
4. **Auditoria**: Alterações críticas geram logs de auditoria

## Tecnologias

- Laravel 12
- PHP 8.2+
- MySQL/PostgreSQL
- Spatie Laravel Permission
- Tailwind CSS (via Vite)

## Licença

Este projeto é proprietário.
