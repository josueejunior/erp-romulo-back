<?php

namespace App\Contracts;

use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface para padronizar services
 * Define métodos que services devem implementar para trabalhar com controllers padrão
 */
interface IService
{
    /**
     * Criar parâmetros para busca por ID
     * @param array $values Valores da requisição
     * @return array Parâmetros formatados
     */
    public function createFindByIdParamBag(array $values): array;

    /**
     * Buscar registro por ID
     * @param int|string $id ID do registro
     * @param array $params Parâmetros adicionais
     * @return Model|null
     */
    public function findById(int|string $id, array $params = []): ?Model;

    /**
     * Criar parâmetros para listagem
     * @param array $values Valores da requisição
     * @return array Parâmetros formatados
     */
    public function createListParamBag(array $values): array;

    /**
     * Listar registros com paginação
     * @param array $params Parâmetros de busca
     * @return LengthAwarePaginator
     */
    public function list(array $params = []): LengthAwarePaginator;

    /**
     * Validar dados para criação
     * @param array $data Dados a validar
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator;

    /**
     * Criar novo registro
     * @param array $data Dados validados
     * @return Model
     */
    public function store(array $data): Model;

    /**
     * Validar dados para atualização
     * @param array $data Dados a validar
     * @param int|string $id ID do registro
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator;

    /**
     * Atualizar registro
     * @param int|string $id ID do registro
     * @param array $data Dados validados
     * @return Model
     */
    public function update(int|string $id, array $data): Model;

    /**
     * Excluir registro por ID
     * @param int|string $id ID do registro
     * @return bool
     */
    public function deleteById(int|string $id): bool;

    /**
     * Excluir múltiplos registros
     * @param array $ids IDs dos registros
     * @return int Quantidade de registros excluídos
     */
    public function deleteByIds(array $ids): int;
}







