-- Script SQL para remover empresa_id da tabela processos no SQLite
-- Execute este script diretamente no banco do tenant se a migration falhar

PRAGMA foreign_keys=OFF;

-- Limpar tabelas temporárias
DROP TABLE IF EXISTS __temp__processos;
DROP TABLE IF EXISTS processos_new;

-- Criar nova tabela sem empresa_id
CREATE TABLE processos_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    orgao_id INTEGER NOT NULL,
    setor_id INTEGER NOT NULL,
    modalidade TEXT NOT NULL CHECK(modalidade IN ('dispensa', 'pregao')),
    numero_modalidade TEXT NOT NULL,
    numero_processo_administrativo TEXT,
    srp INTEGER NOT NULL DEFAULT 0,
    objeto_resumido TEXT NOT NULL,
    data_hora_sessao_publica DATETIME NOT NULL,
    endereco_entrega TEXT,
    forma_prazo_entrega TEXT,
    prazo_pagamento TEXT,
    validade_proposta TEXT,
    tipo_selecao_fornecedor TEXT,
    tipo_disputa TEXT,
    status TEXT NOT NULL DEFAULT 'participacao' CHECK(status IN ('participacao', 'julgamento_habilitacao', 'vencido', 'perdido', 'execucao', 'arquivado')),
    observacoes TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY (orgao_id) REFERENCES orgaos(id),
    FOREIGN KEY (setor_id) REFERENCES setors(id)
);

-- Copiar dados da tabela antiga
INSERT INTO processos_new 
(id, orgao_id, setor_id, modalidade, numero_modalidade, numero_processo_administrativo,
 srp, objeto_resumido, data_hora_sessao_publica, endereco_entrega, forma_prazo_entrega,
 prazo_pagamento, validade_proposta, tipo_selecao_fornecedor, tipo_disputa, status,
 observacoes, created_at, updated_at, deleted_at)
SELECT 
    id, orgao_id, setor_id, modalidade, numero_modalidade, numero_processo_administrativo,
    srp, objeto_resumido, data_hora_sessao_publica, endereco_entrega, forma_prazo_entrega,
    prazo_pagamento, validade_proposta, tipo_selecao_fornecedor, tipo_disputa, status,
    observacoes, created_at, updated_at, deleted_at
FROM processos;

-- Remover tabela antiga
DROP TABLE processos;

-- Renomear nova tabela
ALTER TABLE processos_new RENAME TO processos;

PRAGMA foreign_keys=ON;



