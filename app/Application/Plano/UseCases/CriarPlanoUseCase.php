<?php

namespace App\Application\Plano\UseCases;

use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Plano\Entities\Plano;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Criar Plano
 */
class CriarPlanoUseCase
{
    public function __construct(
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param array $dados Dados do plano
     * @return Plano
     */
    public function executar(array $dados): Plano
    {
        Log::info('CriarPlanoUseCase::executar iniciado', [
            'dados' => $dados,
        ]);

        // Validar dados básicos
        if (empty($dados['nome'])) {
            throw new DomainException('O nome do plano é obrigatório.');
        }

        try {
            return DB::transaction(function () use ($dados) {
                // Criar entidade de domínio
                $plano = Plano::criar(
                    nome: $dados['nome'],
                    descricao: $dados['descricao'] ?? null,
                    precoMensal: $dados['preco_mensal'] ?? 0,
                    precoAnual: $dados['preco_anual'] ?? null,
                    limiteProcessos: $dados['limite_processos'] ?? null,
                    limiteUsuarios: $dados['limite_usuarios'] ?? null,
                    limiteArmazenamentoMb: $dados['limite_armazenamento_mb'] ?? null,
                    recursosDisponiveis: $dados['recursos_disponiveis'] ?? [],
                    ativo: $dados['ativo'] ?? true,
                    ordem: $dados['ordem'] ?? 0,
                );

                // Salvar no repositório
                $planoSalvo = $this->planoRepository->salvar($plano);

                Log::info('CriarPlanoUseCase::executar concluído', [
                    'plano_id' => $planoSalvo->id,
                    'nome' => $planoSalvo->nome,
                ]);

                return $planoSalvo;
            });
        } catch (\Exception $e) {
            Log::error('CriarPlanoUseCase::executar falhou', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}



