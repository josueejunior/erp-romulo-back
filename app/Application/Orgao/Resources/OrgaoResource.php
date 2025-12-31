<?php

namespace App\Application\Orgao\Resources;

use App\Domain\Orgao\Entities\Orgao;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use App\Models\Orgao as OrgaoModel;

/**
 * Resource para transformar entidade Orgao em array de resposta
 * Segue padrão DDD: Controller → Use Case → Domain → Repository
 * Resource transforma Domain Entity → Response Array
 */
class OrgaoResource
{
    public function __construct(
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    /**
     * Transformar entidade Orgao em array de resposta
     * 
     * @param Orgao $orgao Entidade do domínio
     * @return array Array para resposta JSON
     */
    public function toArray(Orgao $orgao): array
    {
        // Buscar modelo Eloquent para incluir relacionamentos (setors)
        $orgaoModel = $this->orgaoRepository->buscarModeloPorId($orgao->id, ['setors']);
        
        return [
            'id' => $orgao->id,
            'empresa_id' => $orgao->empresaId,
            'uasg' => $orgao->uasg,
            'razao_social' => $orgao->razaoSocial,
            'cnpj' => $orgao->cnpj,
            'cep' => $orgao->cep,
            'logradouro' => $orgao->logradouro,
            'numero' => $orgao->numero,
            'bairro' => $orgao->bairro,
            'complemento' => $orgao->complemento,
            'cidade' => $orgao->cidade,
            'estado' => $orgao->estado,
            'email' => $orgao->email,
            'telefone' => $orgao->telefone,
            'emails' => $orgao->emails,
            'telefones' => $orgao->telefones,
            'observacoes' => $orgao->observacoes,
            'setors' => $orgaoModel?->setors?->map(fn($setor) => [
                'id' => $setor->id,
                'nome' => $setor->nome,
                'orgao_id' => $setor->orgao_id,
            ]) ?? [],
        ];
    }
}

