# 📊 Análise de Índices nas Migrations

## 🎯 Objetivo

Verificar e adicionar índices faltantes nas migrations para melhorar performance de queries frequentes.

## ⚡ Regra de Ouro

**Sempre indexar:**
- ✅ `empresa_id` (se aplicável)
- ✅ `user_id` (se aplicável)
- ✅ `status` (se aplicável)
- ✅ `data_inicio`, `data_fim` (se aplicável)
- ✅ Campos usados em `WHERE` frequentes
- ✅ Índices compostos para queries com múltiplos filtros

## 📋 Análise por Tabela

### ✅ Tabelas com Índices Adequados

#### `notificacoes`
```php
$table->index(['usuario_id', 'empresa_id']);
$table->index(['empresa_id', 'tipo']);
$table->index(['created_at']);
```
✅ **Bom** - Índices compostos bem pensados

#### `audit_logs`
```php
$table->index(['model_type', 'model_id']);
$table->index('usuario_id');
$table->index('action');
$table->index(Blueprint::CREATED_AT);
```
✅ **Bom** - Cobre queries principais

### ⚠️ Tabelas que Precisam de Índices

#### `processos`
**Faltam índices em:**
- ❌ `status` (usado em filtros frequentes)
- ❌ `data_hora_sessao_publica` (usado em queries de calendário)
- ❌ `empresa_id` (já tem via foreignEmpresa, mas verificar se indexa)
- ❌ `status_participacao` (usado em filtros)
- ❌ Índice composto `['empresa_id', 'status']`

**Recomendação:**
```php
$table->index('status');
$table->index('data_hora_sessao_publica');
$table->index('status_participacao');
$table->index(['empresa_id', 'status']);
```

#### `autorizacoes_fornecimento`
**Faltam índices em:**
- ❌ `situacao` (usado em filtros)
- ❌ `data` (usado em queries por data)
- ❌ `data_fim_vigencia` (usado para verificar vigência)
- ❌ `vigente` (usado em filtros)
- ❌ `processo_id` (já tem FK, mas verificar índice)

**Recomendação:**
```php
$table->index('situacao');
$table->index('data');
$table->index('data_fim_vigencia');
$table->index('vigente');
$table->index(['empresa_id', 'situacao']);
```

#### `contratos`
**Faltam índices em:**
- ❌ `situacao` (usado em filtros)
- ❌ `data_inicio`, `data_fim` (usado em queries por período)
- ❌ `vigente` (usado em filtros)
- ❌ `processo_id` (já tem FK, mas verificar índice)

**Recomendação:**
```php
$table->index('situacao');
$table->index('data_inicio');
$table->index('data_fim');
$table->index('vigente');
$table->index(['empresa_id', 'vigente']);
```

#### `empenhos`
**Faltam índices em:**
- ❌ `situacao` (usado em filtros)
- ❌ `data` (usado em queries por data)
- ❌ `concluido` (usado em filtros)
- ❌ `processo_id` (já tem FK, mas verificar índice)

**Recomendação:**
```php
$table->index('situacao');
$table->index('data');
$table->index('concluido');
$table->index(['empresa_id', 'situacao']);
```

#### `assinaturas`
**Verificar se tem:**
- ❌ `user_id` (usado em queries por usuário)
- ❌ `status` (usado em filtros)
- ❌ `data_inicio`, `data_fim` (usado em queries por período)
- ❌ Índice composto `['user_id', 'status']`

**Recomendação:**
```php
$table->index('user_id');
$table->index('status');
$table->index('data_inicio');
$table->index('data_fim');
$table->index(['user_id', 'status']);
```

## 🔧 Como Adicionar Índices

### Opção 1: Migration de Alteração (Recomendado)

```bash
php artisan make:migration add_indexes_to_processos_table \
  --path=database/migrations/tenant/processos
```

```php
public function up(): void
{
    Schema::table('processos', function (Blueprint $table) {
        $table->index('status');
        $table->index('data_hora_sessao_publica');
        $table->index(['empresa_id', 'status']);
    });
}
```

### Opção 2: Editar Migration Original (Apenas em Dev)

⚠️ **Só fazer se a migration ainda não foi executada em produção!**

## 📝 Checklist de Implementação

- [ ] Analisar todas as migrations tenant
- [ ] Identificar campos usados em `WHERE` frequentes
- [ ] Criar migrations de alteração para adicionar índices
- [ ] Testar queries após adicionar índices
- [ ] Documentar índices adicionados

## 🎯 Prioridade

**Alta:**
- `processos.status`
- `assinaturas.user_id`, `assinaturas.status`
- `autorizacoes_fornecimento.situacao`

**Média:**
- Campos de data usados em filtros
- Índices compostos para queries complexas

**Baixa:**
- Campos raramente filtrados

