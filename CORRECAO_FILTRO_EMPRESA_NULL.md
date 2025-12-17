# 🔧 Correção: Filtro empresa_id com whereNotNull

## Problema Identificado

Registros com `empresa_id = NULL` podem estar aparecendo em listagens quando:
1. A migration ainda não foi executada
2. Registros antigos têm `empresa_id = NULL`
3. A query `where('empresa_id', $empresa->id)` não filtra explicitamente `NULL`

## Solução Aplicada

Adicionado `whereNotNull('empresa_id')` em todos os controllers principais para garantir que:
- ✅ Apenas registros com `empresa_id` definido apareçam
- ✅ Registros com `NULL` sejam completamente excluídos
- ✅ Não haja vazamento de dados entre empresas

## Controllers Corrigidos

### ✅ Filtros Atualizados:
1. **OrgaoController** - `index()` agora inclui `whereNotNull('empresa_id')`
2. **SetorController** - `index()` agora inclui `whereNotNull('empresa_id')`
3. **FornecedorController** - `index()` agora inclui `whereNotNull('empresa_id')`
4. **CustoIndiretoController** - `index()` agora inclui `whereNotNull('empresa_id')`
5. **ProcessoController** - `index()` agora inclui `whereNotNull('empresa_id')`

## ⚠️ Importante

### Executar Migrations
```bash
php artisan tenants:migrate --force
```

Isso adicionará a coluna `empresa_id` nas tabelas:
- `orgaos`
- `setors`
- `custo_indiretos`

### Dados Existentes
Após executar as migrations, registros existentes terão `empresa_id = NULL`. 

**Opções:**
1. **Começar do zero** (recomendado para testes)
2. **Atribuir empresa_id aos registros existentes** via SQL:
   ```sql
   -- Substitua EMPRESA_ID pelo ID da empresa
   UPDATE orgaos SET empresa_id = EMPRESA_ID WHERE empresa_id IS NULL;
   UPDATE setors SET empresa_id = EMPRESA_ID WHERE empresa_id IS NULL;
   UPDATE custo_indiretos SET empresa_id = EMPRESA_ID WHERE empresa_id IS NULL;
   ```

## ✅ Resultado

Agora, mesmo que existam registros com `empresa_id = NULL`, eles **não aparecerão** nas listagens, garantindo isolamento completo.
