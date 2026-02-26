<?php

namespace App\Application\Processo\UseCases;

use App\Application\Processo\DTOs\CriarProcessoDTO;
use App\Application\Shared\Traits\HasApplicationContext;
use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Processo
 * 
 * Usa o trait HasApplicationContext para resolver empresa_id de forma robusta.
 */
class CriarProcessoUseCase
{
    use HasApplicationContext;
    
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(CriarProcessoDTO $dto): Processo
    {
        // Resolver empresa_id usando o trait (fallbacks robustos)
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        
        // Validar assinatura e limites do plano (regra de negócio)
        $tenant = tenancy()->tenant;
        
        if (!$tenant) {
            \Log::warning('CriarProcessoUseCase::executar() - Tenant não encontrado', [
                'empresa_id' => $empresaId,
            ]);
            throw new DomainException('Tenant não encontrado. Verifique sua assinatura.');
        }

        // Verificar assinatura ativa primeiro
        if (!$tenant->temAssinaturaAtiva()) {
            \Log::warning('CriarProcessoUseCase::executar() - Assinatura não está ativa', [
                'tenant_id' => $tenant->id,
                'assinatura_atual_id' => $tenant->assinatura_atual_id,
            ]);
            throw new DomainException('Você não tem uma assinatura ativa. Ative sua assinatura para criar processos.');
        }

        $plano = $tenant->planoAtual;
        if (!$plano) {
            \Log::warning('CriarProcessoUseCase::executar() - Plano não encontrado', [
                'tenant_id' => $tenant->id,
                'plano_atual_id' => $tenant->plano_atual_id,
            ]);
            throw new DomainException('Plano não encontrado. Verifique sua assinatura.');
        }

        // Restrição diária pela DATA DO PROCESSO (data da sessão): não permitir 2 processos na mesma data de sessão
        if ($plano->temRestricaoDiaria() && $dto->dataHoraSessaoPublica !== null) {
            $dataSessao = $dto->dataHoraSessaoPublica->format('Y-m-d');
            $jaExisteNestaData = \App\Modules\Processo\Models\Processo::where('empresa_id', $empresaId)
                ->whereDate('data_hora_sessao_publica', $dataSessao)
                ->count();
            if ($jaExisteNestaData >= 1) {
                \Log::info('CriarProcessoUseCase::executar() - Bloqueado: já existe processo com essa data de sessão', [
                    'tenant_id' => $tenant->id,
                    'empresa_id' => $empresaId,
                    'data_sessao' => $dataSessao,
                ]);
                throw new DomainException('Já existe um processo com a data de sessão ' . $dto->dataHoraSessaoPublica->format('d/m/Y') . '. Planos com restrição diária permitem apenas 1 processo por data de sessão.');
            }
        }

        // Verificar se pode criar processo (limite por data de criação: 1 por dia; e limite mensal)
        if (!$tenant->podeCriarProcesso()) {
            \Log::info('CriarProcessoUseCase::executar() - Bloqueado por limite do plano', [
                'tenant_id' => $tenant->id,
                'plano_id' => $plano->id,
                'plano_nome' => $plano->nome,
                'limite_processos' => $plano->limite_processos,
                'restricao_diaria' => $plano->temRestricaoDiaria(),
            ]);
            
            // Verificar se é restrição diária ou limite mensal
            if ($plano->temRestricaoDiaria()) {
                throw new DomainException('Você já criou um processo hoje. Planos Essencial e Profissional permitem apenas 1 processo por dia.');
            }
            
            // Verificar limite mensal
            if (!$plano->temProcessosIlimitados()) {
                $limite = $plano->limite_processos;
                throw new DomainException("Você atingiu o limite de {$limite} processos do seu plano. Faça upgrade para continuar criando processos.");
            }
            
            throw new DomainException('Você não pode criar processos no momento. Verifique sua assinatura.');
        }
        
        // Status padrão
        $status = $dto->status ?: 'participacao';
        
        // Criar entidade Processo (regras de negócio)
        $processo = new Processo(
            id: null, // Será gerado pelo repository
            empresaId: $empresaId,
            orgaoId: $dto->orgaoId,
            setorId: $dto->setorId,
            modalidade: $dto->modalidade,
            numeroModalidade: $dto->numeroModalidade,
            numeroProcessoAdministrativo: $dto->numeroProcessoAdministrativo,
            linkEdital: $dto->linkEdital,
            portal: $dto->portal,
            numeroEdital: $dto->numeroEdital,
            srp: $dto->srp,
            objetoResumido: $dto->objetoResumido,
            dataHoraSessaoPublica: $dto->dataHoraSessaoPublica,
            horarioSessaoPublica: $dto->horarioSessaoPublica,
            enderecoEntrega: $dto->enderecoEntrega,
            localEntregaDetalhado: $dto->localEntregaDetalhado,
            formaEntrega: $dto->formaEntrega,
            prazoEntrega: $dto->prazoEntrega,
            formaPrazoEntrega: $dto->formaPrazoEntrega,
            prazosDetalhados: $dto->prazosDetalhados,
            prazoPagamento: $dto->prazoPagamento,
            validadeProposta: $dto->validadeProposta,
            validadePropostaInicio: $dto->validadePropostaInicio,
            validadePropostaFim: $dto->validadePropostaFim,
            tipoSelecaoFornecedor: $dto->tipoSelecaoFornecedor,
            tipoDisputa: $dto->tipoDisputa,
            status: $status,
            statusParticipacao: $dto->statusParticipacao,
            dataRecebimentoPagamento: $dto->dataRecebimentoPagamento,
            observacoes: $dto->observacoes,
        );

        // Persistir processo (infraestrutura)
        return $this->processoRepository->criar($processo);
    }
}




