-- INSERT para tabela tenants
-- Estrutura: id, razao_social, cnpj, email, status, data, created_at, updated_at
-- Formato de data: YYYY-MM-DD HH:MM:SS

INSERT INTO tenants (id, razao_social, cnpj, email, status, data, created_at, updated_at)
VALUES (
    'empresa-exemplo',
    'Empresa Exemplo LTDA',
    '12.345.678/0001-90',
    'contato@exemplo.com',
    'ativa',
    NULL,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

-- Exemplo de mais tenants (descomente se precisar)
/*
INSERT INTO tenants (id, razao_social, cnpj, email, status, data, created_at, updated_at)
VALUES (
    'empresa-teste',
    'Empresa Teste EIRELI',
    '98.765.432/0001-10',
    'contato@teste.com',
    'ativa',
    NULL,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

INSERT INTO tenants (id, razao_social, cnpj, email, status, data, created_at, updated_at)
VALUES (
    'minha-empresa',
    'Minha Empresa LTDA',
    '11.222.333/0001-44',
    'contato@minhaempresa.com.br',
    'ativa',
    NULL,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);
*/
