# 🔧 Correção: Adicionar empresa_id em Todas as Tabelas

## Problema

A tabela `setors` (e possivelmente outras) não possui a coluna `empresa_id`, causando erro:
```
SQLSTATE[42703]: Undefined column: column "empresa_id" does not exist
```

## Solução

Foi criada uma migration que garante que todas as tabelas tenham `empresa_id`:
- `2025_01_22_000001_ensure_empresa_id_in_all_tables.php`

## 📋 Como Executar

### 1. Executar migrations nos tenants

```bash
# Executar migrations em todos os tenants
php artisan tenants:migrate --force

# Ou executar em um tenant específico
php artisan tenants:migrate --tenants=tenant-id --force
```

### 2. Verificar se a coluna foi adicionada

```bash
# Conectar ao banco do tenant e verificar
# Exemplo para PostgreSQL:
psql -h localhost -U seu_usuario -d tenant_db
\d setors
```

### 3. Se ainda houver problemas

Execute a migration manualmente:

```bash
# Listar tenants
php artisan tenants:list

# Para cada tenant, executar:
php artisan tenants:migrate --tenants=tenant-id --force
```

## ✅ Tabelas que serão corrigidas

A migration verifica e adiciona `empresa_id` nas seguintes tabelas:

1. ✅ `setors`
2. ✅ `orgaos`
3. ✅ `custo_indiretos`
4. ✅ `fornecedores`
5. ✅ `processos`
6. ✅ `orcamentos`
7. ✅ `contratos`
8. ✅ `empenhos`
9. ✅ `notas_fiscais`
10. ✅ `autorizacoes_fornecimento`
11. ✅ `documentos_habilitacao`

## 🔍 Verificação

Após executar as migrations, verifique se todas as tabelas têm `empresa_id`:

```sql
-- PostgreSQL
SELECT table_name 
FROM information_schema.columns 
WHERE column_name = 'empresa_id' 
AND table_schema = 'public';
```

## ⚠️ Importante

- A migration só adiciona a coluna se ela não existir
- A coluna é criada como `nullable` para não quebrar dados existentes
- A foreign key é criada com `onDelete('cascade')` para manter integridade

## 🚨 Se o erro persistir

1. Verifique se a migration foi executada:
   ```bash
   php artisan tenants:migrate:status --tenants=tenant-id
   ```

2. Execute novamente:
   ```bash
   php artisan tenants:migrate --tenants=tenant-id --force
   ```

3. Se necessário, execute SQL manualmente:
   ```sql
   ALTER TABLE setors 
   ADD COLUMN empresa_id BIGINT NULL;
   
   ALTER TABLE setors 
   ADD CONSTRAINT setors_empresa_id_foreign 
   FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE;
   ```
