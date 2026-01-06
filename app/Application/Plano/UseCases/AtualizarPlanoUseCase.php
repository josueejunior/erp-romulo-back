<?php

namespace App\Application\Plano\UseCases;

use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\DomainException;

/**
 * Use Case: Atualizar Plano
 */
class AtualizarPlanoUseCase
{
    public function __construct(
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param int $id ID do plano
     * @param array $dados Dados atualizados
     * @return \App\Domain\Plano\Entities\Plano
     */
    public function executar(int $id, array $dados): \App\Domain\Plano\Entities\Plano
    {
        // Buscar plano existente
        $plano = $this->planoRepository->buscarPorId($id);
        
        if (!$plano) {
            throw new NotFoundException("Plano não encontrado.");
        }

        // Atualizar propriedades
        if (isset($dados['nome'])) {
            $plano->setNome($dados['nome']);
        }
        
        if (isset($dados['descricao'])) {
            $plano->setDescricao($dados['descricao']);
        }
        
        if (isset($dados['preco_mensal'])) {
            $plano->setPrecoMensal($dados['preco_mensal']);
        }
        
        if (isset($dados['preco_anual'])) {
            $plano->setPrecoAnual($dados['preco_anual']);
        }
        
        if (isset($dados['limite_processos'])) {
            $plano->setLimiteProcessos($dados['limite_processos']);
        }
        
        if (isset($dados['limite_usuarios'])) {
            $plano->setLimiteUsuarios($dados['limite_usuarios']);
        }
        
        if (isset($dados['limite_armazenamento_mb'])) {
            $plano->setLimiteArmazenamentoMb($dados['limite_armazenamento_mb']);
        }
        
        if (isset($dados['recursos_disponiveis'])) {
            $plano->setRecursosDisponiveis($dados['recursos_disponiveis']);
        }
        
        if (isset($dados['ativo'])) {
            $plano->setAtivo($dados['ativo']);
        }
        
        if (isset($dados['ordem'])) {
            $plano->setOrdem($dados['ordem']);
        }

        // Salvar alterações
        return $this->planoRepository->salvar($plano);
    }
}


