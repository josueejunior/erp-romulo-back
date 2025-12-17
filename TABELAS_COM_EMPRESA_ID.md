# 📊 Tabelas com empresa_id

## ✅ Tabelas que RECEBERÃO empresa_id (via migration)

A migration `2025_12_17_120001_add_empresa_id_to_all_tables.php` adiciona `empresa_id` nas seguintes tabelas:

1. ✅ **processos** - `empresa_id` após `id`
2. ✅ **orcamentos** - `empresa_id` após `id`
3. ✅ **contratos** - `empresa_id` após `id`
4. ✅ **empenhos** - `empresa_id` após `id`
5. ✅ **notas_fiscais** - `empresa_id` após `id`
6. ✅ **autorizacoes_fornecimento** - `empresa_id` após `id`
7. ✅ **fornecedores** - `empresa_id` após `id`

A migration `2025_12_17_120000_add_empresa_id_to_documentos_habilitacao_table.php` adiciona:

8. ✅ **documentos_habilitacao** - `empresa_id` após `id`

## 📋 Estrutura da coluna empresa_id

Todas as colunas `empresa_id` têm:
- Tipo: `foreignId('empresa_id')`
- Nullable: `true` (para permitir dados existentes)
- Posição: `after('id')`
- Foreign Key: `constrained('empresas')->onDelete('cascade')`
- Comportamento: Quando empresa é excluída, todos os registros relacionados são excluídos

## 🔍 Tabelas que NÃO precisam de empresa_id

Estas tabelas não precisam de `empresa_id` porque:
- São relacionadas a outras tabelas que já têm `empresa_id`
- Ou são tabelas de configuração do sistema

1. **processo_itens** - Herda empresa_id do processo
2. **processo_documentos** - Herda empresa_id do processo
3. **orcamento_itens** - Herda empresa_id do orcamento
4. **formacao_precos** - Herda empresa_id do orcamento/item
5. **processo_item_vinculos** - Herda empresa_id do processo
6. **transportadoras** - Pode herdar de fornecedor (se fornecedor tiver empresa_id)
7. **orgaos** - Tabela de configuração (pode precisar se houver isolamento)
8. **setors** - Tabela de configuração (pode precisar se houver isolamento)
9. **custos_indiretos** - Verificar se precisa de isolamento

## ⚠️ IMPORTANTE

Após executar as migrations, **todos os registros existentes terão `empresa_id = NULL`**.

Para corrigir dados existentes, você precisará:
1. Executar um script para atribuir `empresa_id` aos registros existentes
2. Ou começar do zero (como você mencionou)

## 🚀 Como executar

```bash
php artisan tenants:migrate --force
```

Isso adicionará `empresa_id` em todas as tabelas listadas acima.
