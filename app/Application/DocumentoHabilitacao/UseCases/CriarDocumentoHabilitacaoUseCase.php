<?php

namespace App\Application\DocumentoHabilitacao\UseCases;

use App\Application\DocumentoHabilitacao\DTOs\CriarDocumentoHabilitacaoDTO;
use App\Domain\DocumentoHabilitacao\Entities\DocumentoHabilitacao;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Use Case: Criar Documento de Habilitação
 */
class CriarDocumentoHabilitacaoUseCase
{
    public function __construct(
        private DocumentoHabilitacaoRepositoryInterface $documentoRepository,
    ) {}

    public function executar(CriarDocumentoHabilitacaoDTO $dto): DocumentoHabilitacao
    {
        $context = TenantContext::get();

        if (!$context->empresaId) {
            throw new DomainException('Empresa não identificada no contexto. Verifique se o middleware está configurado corretamente.');
        }

        $documento = new DocumentoHabilitacao(
            id: null,
            empresaId: $context->empresaId, // Get empresaId from context
            tipo: $dto->tipo,
            numero: $dto->numero,
            identificacao: $dto->identificacao,
            dataEmissao: $dto->dataEmissao,
            dataValidade: $dto->dataValidade,
            arquivo: $dto->arquivo,
            ativo: $dto->ativo,
            observacoes: $dto->observacoes,
        );

        return $this->documentoRepository->criar($documento);
    }
}


