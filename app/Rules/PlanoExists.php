<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Regra de validação para verificar se um plano existe no banco central
 * 
 * 🔥 IMPORTANTE: Esta regra sempre usa a conexão central (pgsql) para validar,
 * mesmo quando o código está no contexto do tenant, pois a tabela planos está
 * no banco central, não no banco do tenant.
 */
class PlanoExists implements ValidationRule
{
    protected $connection;

    public function __construct()
    {
        // Sempre usar conexão central para validar planos
        $this->connection = config('tenancy.database.central_connection', 'pgsql');
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (empty($value)) {
            $fail('O :attribute é obrigatório.');
            return;
        }

        try {
            // Usar conexão central para validar
            $exists = DB::connection($this->connection)
                ->table('planos')
                ->where('id', $value)
                ->exists();

            if (!$exists) {
                $fail('O plano selecionado não existe.');
            }
        } catch (\Exception $e) {
            // Se houver erro ao acessar o banco central, falhar validação
            $fail('Erro ao validar o plano. Tente novamente.');
        }
    }
}

