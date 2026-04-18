# 🔧 Solução: Órgãos de Outras Empresas Aparecendo

## Problema Identificado

Órgãos de outras empresas estão aparecendo na listagem porque:
1. **Registros com `empresa_id = NULL`** podem estar sendo retornados
2. A migration pode não ter sido executada ainda
3. Registros antigos podem ter `empresa_id = NULL`

## Solução Aplicada

Adicionado `whereNotNull('empresa_id')` em **todos** os controllers principais para garantir que:
- ✅ Apenas registros com `empresa_id` definido apareçam
- ✅ Registros com `NULL` sejam completamente excluídos
- ✅ Não haja vazamento de dados entre empresas

## Controllers Corrigidos

### ✅ Filtros Atualizados com `whereNotNull('empresa_id')`:
1. **OrgaoController** - `index()`
2. **SetorController** - `index()`
3. **FornecedorController** - `index()`
4. **CustoIndiretoController** - `index()`, `resumo()`
5. **ProcessoController** - `index()`, `resumo()`, `exportar()`
6. **ContratoController** - `listarTodos()`
7. **DocumentoHabilitacaoController** - `index()`
8. **DashboardController** - Todas as queries de Processo e DocumentoHabilitacao
9. **CalendarioDisputasController** - `index()`, `eventos()`
10. **RelatorioFinanceiroController** - Queries de Processo e CustoIndireto

## ⚠️ Ação Necessária

### 1. Executar Migrations
```bash
php artisan tenants:migrate --force
```

Isso adicionará a coluna `empresa_id` nas tabelas:
- `orgaos`
- `setors`
- `custo_indiretos`

### 2. Atribuir empresa_id aos Registros Existentes

Se você tem registros antigos com `empresa_id = NULL`, execute:

```sql
-- No banco do tenant, substitua EMPRESA_ID pelo ID da empresa
UPDATE orgaos SET empresa_id = EMPRESA_ID WHERE empresa_id IS NULL;
UPDATE setors SET empresa_id = EMPRESA_ID WHERE empresa_id IS NULL;
UPDATE custo_indiretos SET empresa_id = EMPRESA_ID WHERE empresa_id IS NULL;
```

### 3. Verificar empresa_id do Usuário

Certifique-se de que o usuário tem `empresa_ativa_id` definido:

```sql
-- No banco do tenant
SELECT id, email, empresa_ativa_id FROM users WHERE email = 'seu_email@exemplo.com';
```

Se `empresa_ativa_id` for NULL, defina:

```sql
UPDATE users SET empresa_ativa_id = EMPRESA_ID WHERE email = 'seu_email@exemplo.com';
```

## ✅ Resultado

Agora, mesmo que existam registros com `empresa_id = NULL`, eles **não aparecerão** nas listagens, garantindo isolamento completo.

**Teste:**
1. Acesse `/orgaos` no frontend
2. Deve aparecer apenas órgãos da empresa ativa
3. Troque de empresa e verifique que apenas órgãos da nova empresa aparecem
