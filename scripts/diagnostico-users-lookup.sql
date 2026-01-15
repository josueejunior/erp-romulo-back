-- Script SQL para diagnóstico e correção da tabela users_lookup
-- Execute no banco CENTRAL (não no banco do tenant)

-- 1. Verificar se a tabela existe e contar registros
SELECT 
    'Total de registros (sem deleted_at)' as descricao,
    COUNT(*) as total
FROM users_lookup
WHERE deleted_at IS NULL;

-- 2. Contar por status
SELECT 
    status,
    COUNT(*) as total
FROM users_lookup
WHERE deleted_at IS NULL
GROUP BY status
ORDER BY status;

-- 3. Verificar registros ativos
SELECT 
    'Registros com status ativo' as descricao,
    COUNT(*) as total
FROM users_lookup
WHERE deleted_at IS NULL 
  AND status = 'ativo';

-- 4. Verificar se há registros duplicados (mesmo email + tenant)
SELECT 
    email,
    tenant_id,
    COUNT(*) as duplicados
FROM users_lookup
WHERE deleted_at IS NULL
GROUP BY email, tenant_id
HAVING COUNT(*) > 1;

-- 5. Verificar registros sem email ou CNPJ
SELECT 
    'Registros sem email' as problema,
    COUNT(*) as total
FROM users_lookup
WHERE deleted_at IS NULL 
  AND (email IS NULL OR email = '');

SELECT 
    'Registros sem CNPJ' as problema,
    COUNT(*) as total
FROM users_lookup
WHERE deleted_at IS NULL 
  AND (cnpj IS NULL OR cnpj = '');

-- 6. Listar primeiros 10 registros para inspeção
SELECT 
    id,
    email,
    cnpj,
    tenant_id,
    user_id,
    empresa_id,
    status,
    created_at,
    updated_at
FROM users_lookup
WHERE deleted_at IS NULL
ORDER BY created_at DESC
LIMIT 10;

-- 7. CORREÇÃO: Atualizar todos os registros para status 'ativo' se estiverem NULL ou vazio
-- DESCOMENTE APENAS SE NECESSÁRIO:
-- UPDATE users_lookup
-- SET status = 'ativo'
-- WHERE deleted_at IS NULL 
--   AND (status IS NULL OR status = '');

-- 8. CORREÇÃO: Popular tabela (execute o comando Laravel ao invés disso)
-- php artisan users:popular-lookup --force

