# 🔍 Debug Completo: Órgãos Não Filtrados por Empresa

## Problema

Os órgãos ainda não estão sendo filtrados corretamente pela empresa selecionada.

## Logs Adicionados

Adicionados logs detalhados em várias etapas:

1. **Debug Inicial** - Mostra qual empresa está sendo usada
2. **Query SQL** - Mostra a query que será executada
3. **Estatísticas** - Mostra quantos órgãos existem no total, quantos pertencem à empresa, quantos são NULL, etc.
4. **Resultados da Query** - Mostra o que a query retornou ANTES do filtro adicional
5. **Resultados Finais** - Mostra o que foi retornado DEPOIS do filtro adicional
6. **Warnings** - Loga cada órgão que não pertence à empresa

## Verificação Imediata

### 1. Verificar Logs do Backend

```bash
tail -f storage/logs/laravel.log | grep "OrgaoController"
```

Procure por:
- `OrgaoController::index - Debug` - Qual empresa está sendo usada
- `OrgaoController::index - Estatísticas` - Quantos órgãos existem e quantos pertencem à empresa
- `OrgaoController::index - Resultados da Query` - O que a query retornou
- `OrgaoController::index - Órgão não pertence à empresa!` - Warnings de órgãos incorretos

### 2. Verificar Dados no Banco

Execute no banco do tenant:

```sql
-- Ver todos os órgãos e suas empresas
SELECT 
    o.id, 
    o.razao_social, 
    o.empresa_id, 
    e.razao_social as empresa_nome
FROM orgaos o
LEFT JOIN empresas e ON e.id = o.empresa_id
ORDER BY o.empresa_id, o.razao_social;

-- Ver empresa_ativa_id do usuário
SELECT 
    u.id, 
    u.email, 
    u.empresa_ativa_id,
    e.razao_social as empresa_ativa_nome
FROM users u
LEFT JOIN empresas e ON e.id = u.empresa_ativa_id;

-- Ver todas as empresas
SELECT id, razao_social, cnpj FROM empresas ORDER BY id;
```

### 3. Verificar se a Migration Foi Executada

```sql
-- Verificar se a coluna empresa_id existe na tabela orgaos
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'orgaos' AND column_name = 'empresa_id';

-- Verificar se a coluna empresa_ativa_id existe na tabela users
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'users' AND column_name = 'empresa_ativa_id';
```

## Possíveis Causas

### 1. Migration Não Executada
Se a coluna `empresa_id` não existir na tabela `orgaos`, a query não funcionará.

**Solução:**
```bash
php artisan tenants:migrate --force
```

### 2. empresa_ativa_id do Usuário é NULL
Se o usuário não tiver `empresa_ativa_id` definido, `getEmpresaAtivaOrFail()` retornará erro 403.

**Solução:**
```sql
-- Ver qual empresa o usuário deve ter ativa
SELECT id, razao_social FROM empresas;

-- Atualizar empresa_ativa_id do usuário
UPDATE users 
SET empresa_ativa_id = EMPRESA_ID 
WHERE email = 'seu_email@exemplo.com';
```

### 3. Órgãos com empresa_id NULL ou Incorreto
Se os órgãos tiverem `empresa_id = NULL` ou de outra empresa, eles não aparecerão (ou aparecerão incorretamente).

**Solução:**
```sql
-- Ver órgãos sem empresa_id
SELECT id, razao_social, empresa_id FROM orgaos WHERE empresa_id IS NULL;

-- Atribuir empresa_id aos órgãos NULL
UPDATE orgaos 
SET empresa_id = EMPRESA_ID 
WHERE empresa_id IS NULL;
```

### 4. Problema com Tenant Context
Se o tenant não estiver sendo inicializado corretamente, os dados podem estar vindo do banco errado.

**Verificar:**
- Header `X-Tenant-ID` está sendo enviado?
- O tenant existe no banco central?
- O banco do tenant foi criado?

## Próximos Passos

1. **Execute os comandos SQL acima** para verificar os dados
2. **Verifique os logs** do backend para ver exatamente o que está acontecendo
3. **Corrija os dados** se necessário (empresa_id dos órgãos, empresa_ativa_id do usuário)
4. **Execute as migrations** se ainda não foram executadas

## Resultado Esperado nos Logs

Após corrigir os dados, os logs devem mostrar:
- `total_orgaos_empresa_ativa` > 0 (se houver órgãos da empresa)
- `total_orgaos_null` = 0 (não deve haver órgãos sem empresa_id)
- `orgaos_retornados` deve ter apenas órgãos com `empresa_id` igual ao `empresa_ativa_id` do usuário
- Não deve haver warnings de "Órgão não pertence à empresa!"
