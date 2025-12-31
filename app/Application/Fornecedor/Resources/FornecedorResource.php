<?php

namespace App\Application\Fornecedor\Resources;

use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;

/**
 * Resource para transformar entidade Fornecedor em array de resposta
 * Segue padrão DDD: Controller → Use Case → Domain → Repository
 * Resource transforma Domain Entity → Response Array
 */
class FornecedorResource
{
    public function __construct(
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    /**
     * Transformar entidade Fornecedor em array de resposta
     * 
     * @param Fornecedor $fornecedor Entidade do domínio
     * @return array Array para resposta JSON
     */
    public function toArray(Fornecedor $fornecedor): array
    {
        return [
            'id' => $fornecedor->id,
            'empresa_id' => $fornecedor->empresaId,
            'razao_social' => $fornecedor->razaoSocial,
            'cnpj' => $fornecedor->cnpj,
            'nome_fantasia' => $fornecedor->nomeFantasia,
            'cep' => $fornecedor->cep,
            'logradouro' => $fornecedor->logradouro,
            'numero' => $fornecedor->numero,
            'bairro' => $fornecedor->bairro,
            'complemento' => $fornecedor->complemento,
            'cidade' => $fornecedor->cidade,
            'estado' => $fornecedor->estado,
            'email' => $fornecedor->email,
            'telefone' => $fornecedor->telefone,
            'emails' => $fornecedor->emails,
            'telefones' => $fornecedor->telefones,
            'contato' => $fornecedor->contato,
            'observacoes' => $fornecedor->observacoes,
            'is_transportadora' => $fornecedor->isTransportadora,
        ];
    }
}

