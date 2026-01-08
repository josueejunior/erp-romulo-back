<?php

namespace App\Application\Contrato\UseCases;

use App\Application\Contrato\DTOs\CriarContratoDTO;
use App\Application\Shared\Traits\HasApplicationContext;
use App\Domain\Contrato\Entities\Contrato;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use DomainException;

/**
 * Application Service: CriarContratoUseCase
 * 
 * Usa o trait HasApplicationContext para resolver empresa_id de forma robusta.
 */
class CriarContratoUseCase
{
    use HasApplicationContext;
    
    public function __construct(
        private ContratoRepositoryInterface $contratoRepository,
    ) {}

    public function executar(CriarContratoDTO $dto): Contrato
    {
        // Resolver empresa_id usando o trait (fallbacks robustos)
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        
        // Processar upload do arquivo se presente
        $arquivoPath = $dto->arquivoContrato;
        if ($dto->hasArquivoParaUpload()) {
            $arquivo = $dto->arquivoUpload;
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $caminhoCompleto = $arquivo->storeAs('contratos', $nomeArquivo, 'public');
            $arquivoPath = $caminhoCompleto;
            
            Log::debug('CriarContratoUseCase: Arquivo uploaded', [
                'nome' => $nomeArquivo,
                'caminho' => $caminhoCompleto,
            ]);
        }
        
        // Mapear situacao corretamente - valores permitidos: vigente, encerrado, cancelado
        $situacao = $dto->situacao;
        $situacoesValidas = ['vigente', 'encerrado', 'cancelado'];
        
        // Mapear valores do frontend para valores válidos no banco
        if ($situacao === 'ativo') {
            $situacao = 'vigente';
        } elseif ($situacao === 'suspenso') {
            $situacao = 'cancelado'; // Suspenso mapeia para cancelado
        }
        
        // Se não for um valor válido, usar vigente como padrão
        if (!in_array($situacao, $situacoesValidas)) {
            $situacao = 'vigente';
        }
        
        $contrato = new Contrato(
            id: null,
            empresaId: $empresaId,
            processoId: $dto->processoId,
            numero: $dto->numero,
            dataInicio: $dto->dataInicio,
            dataFim: $dto->dataFim,
            dataAssinatura: $dto->dataAssinatura,
            valorTotal: $dto->valorTotal,
            saldo: $dto->valorTotal, // Saldo inicial = valor total
            valorEmpenhado: 0.0,
            condicoesComerciais: $dto->condicoesComerciais,
            condicoesTecnicas: $dto->condicoesTecnicas,
            locaisEntrega: $dto->locaisEntrega,
            prazosContrato: $dto->prazosContrato,
            regrasContrato: $dto->regrasContrato,
            situacao: $situacao,
            vigente: $dto->vigente,
            observacoes: $dto->observacoes,
            arquivoContrato: $arquivoPath,
            numeroCte: $dto->numeroCte,
        );

        return $this->contratoRepository->criar($contrato);
    }
}


