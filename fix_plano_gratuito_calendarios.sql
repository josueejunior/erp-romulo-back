-- Script SQL para adicionar o recurso 'calendarios' ao plano Gratuito
-- Execute este script no banco de dados central (pgsql)

UPDATE planos
SET recursos_disponiveis = (
    SELECT jsonb_set(
        COALESCE(recursos_disponiveis::jsonb, '[]'::jsonb),
        '{' || (jsonb_array_length(COALESCE(recursos_disponiveis::jsonb, '[]'::jsonb)))::text || '}',
        '"calendarios"'
    )
)
WHERE nome = 'Gratuito'
  AND NOT EXISTS (
    SELECT 1
    FROM jsonb_array_elements_text(COALESCE(recursos_disponiveis::jsonb, '[]'::jsonb)) AS elem
    WHERE elem = 'calendarios'
  );

-- Verificar se foi atualizado
SELECT nome, recursos_disponiveis
FROM planos
WHERE nome = 'Gratuito';

