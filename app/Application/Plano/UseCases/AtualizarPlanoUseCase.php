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
        // Nota: A entidade Plano é imutável, então os setters retornam uma nova instância
        // É necessário atribuir o resultado de volta à variável $plano
        if (isset($dados['nome'])) {
            $plano = $plano->setNome($dados['nome']);
        }
        
        if (isset($dados['descricao'])) {
            $plano = $plano->setDescricao($dados['descricao']);
        }
        
        if (isset($dados['preco_mensal'])) {
            $plano = $plano->setPrecoMensal($dados['preco_mensal']);
        }
        
        if (isset($dados['preco_anual'])) {
            $plano = $plano->setPrecoAnual($dados['preco_anual']);
        }
        
        if (isset($dados['limite_processos'])) {
            $plano = $plano->setLimiteProcessos($dados['limite_processos']);
        }
        
        if (isset($dados['limite_usuarios'])) {
            $plano = $plano->setLimiteUsuarios($dados['limite_usuarios']);
        }
        
        if (isset($dados['limite_armazenamento_mb'])) {
            $plano = $plano->setLimiteArmazenamentoMb($dados['limite_armazenamento_mb']);
        }
        
        if (isset($dados['recursos_disponiveis'])) {
            $plano = $plano->setRecursosDisponiveis($dados['recursos_disponiveis']);
        }
        
        if (isset($dados['ativo'])) {
            $plano = $plano->setAtivo($dados['ativo']);
        }
        
        if (isset($dados['ordem'])) {
            $plano = $plano->setOrdem($dados['ordem']);
        }

        // Salvar alterações
        return $this->planoRepository->salvar($plano);
    }
}



