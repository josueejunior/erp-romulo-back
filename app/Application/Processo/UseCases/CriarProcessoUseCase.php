<?php

namespace App\Application\Processo\UseCases;

use App\Application\Processo\DTOs\CriarProcessoDTO;
use App\Application\Shared\Traits\HasApplicationContext;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Modules\Processo\Models\Processo as ProcessoModel;
use DomainException;

/**
 * Use Case: Criar Processo
 *
 * Usa HasApplicationContext para resolver empresa_id
 * e valida os limites com base na data da sessao publica.
 */
class CriarProcessoUseCase
{
    use HasApplicationContext;

    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(CriarProcessoDTO $dto): Processo
    {
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        $tenant = tenancy()->tenant;

        if (!$tenant) {
            \Log::warning('CriarProcessoUseCase::executar() - Tenant nao encontrado', [
                'empresa_id' => $empresaId,
            ]);
            throw new DomainException('Tenant nao encontrado. Verifique sua assinatura.');
        }

        $assinaturaDomain = $this->assinaturaRepository->buscarAssinaturaAtualPorEmpresa($empresaId, tenancy()->tenant?->id);
        if (!$assinaturaDomain) {
            // Compatibilidade com dados legados por tenant_id
            $assinaturaDomain = $this->assinaturaRepository->buscarAssinaturaAtual((int) $tenant->id);
        }

        if (!$assinaturaDomain) {
            \Log::warning('CriarProcessoUseCase::executar() - Assinatura nao encontrada', [
                'tenant_id' => $tenant->id,
                'empresa_id' => $empresaId,
            ]);
            throw new DomainException('Voce nao tem uma assinatura ativa. Ative sua assinatura para criar processos.');
        }

        $assinaturaModel = $this->assinaturaRepository->buscarModeloPorId($assinaturaDomain->id);
        if (!$assinaturaModel || !$assinaturaModel->plano) {
            \Log::warning('CriarProcessoUseCase::executar() - Plano nao encontrado', [
                'tenant_id' => $tenant->id,
                'empresa_id' => $empresaId,
                'assinatura_id' => $assinaturaDomain->id,
            ]);
            throw new DomainException('Plano nao encontrado. Verifique sua assinatura.');
        }

        if (!$assinaturaModel->isAtiva()) {
            \Log::warning('CriarProcessoUseCase::executar() - Assinatura nao ativa', [
                'tenant_id' => $tenant->id,
                'empresa_id' => $empresaId,
                'assinatura_id' => $assinaturaModel->id,
                'status' => $assinaturaModel->status,
            ]);
            throw new DomainException('Voce nao tem uma assinatura ativa. Ative sua assinatura para criar processos.');
        }

        $plano = $assinaturaModel->plano;
        $dataSessaoPublica = $dto->dataHoraSessaoPublica?->copy();

        if (!$dataSessaoPublica) {
            throw new DomainException('Data da sessao publica e obrigatoria para validar os limites do plano.');
        }

        // Regra diaria: 1 processo por dia da sessao publica (nao por dia de criacao)
        if ($plano->temRestricaoDiaria()) {
            $processosNoDiaDaSessao = ProcessoModel::where('empresa_id', $empresaId)
                ->whereDate('data_hora_sessao_publica', $dataSessaoPublica->toDateString())
                ->count();

            if ($processosNoDiaDaSessao > 0) {
                \Log::info('CriarProcessoUseCase::executar() - Bloqueado por restricao diaria na data da sessao', [
                    'tenant_id' => $tenant->id,
                    'empresa_id' => $empresaId,
                    'plano_id' => $plano->id,
                    'plano_nome' => $plano->nome,
                    'data_sessao_publica' => $dataSessaoPublica->toDateString(),
                    'processos_no_dia' => $processosNoDiaDaSessao,
                ]);

                throw new DomainException(sprintf(
                    'Voce ja possui um processo para a data %s. Planos Essencial e Profissional permitem apenas 1 processo por dia de sessao.',
                    $dataSessaoPublica->format('d/m/Y')
                ));
            }
        }

        // Regra mensal: contabiliza pelo mes da sessao publica
        if (!$plano->temProcessosIlimitados()) {
            $inicioMes = $dataSessaoPublica->copy()->startOfMonth();
            $fimMes = $dataSessaoPublica->copy()->endOfMonth();
            $processosNoMesDaSessao = ProcessoModel::where('empresa_id', $empresaId)
                ->whereBetween('data_hora_sessao_publica', [$inicioMes, $fimMes])
                ->count();

            if ($processosNoMesDaSessao >= $plano->limite_processos) {
                \Log::info('CriarProcessoUseCase::executar() - Bloqueado por limite mensal (mes da sessao)', [
                    'tenant_id' => $tenant->id,
                    'empresa_id' => $empresaId,
                    'plano_id' => $plano->id,
                    'plano_nome' => $plano->nome,
                    'limite_processos' => $plano->limite_processos,
                    'processos_no_mes' => $processosNoMesDaSessao,
                    'referencia_mes' => $inicioMes->format('Y-m'),
                ]);

                throw new DomainException("Voce atingiu o limite de {$plano->limite_processos} processos do seu plano. Faca upgrade para continuar criando processos.");
            }
        }

        $status = $dto->status ?: 'participacao';

        $processo = new Processo(
            id: null,
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

        return $this->processoRepository->criar($processo);
    }
}

