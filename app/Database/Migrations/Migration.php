<?php

namespace App\Database\Migrations;

use Illuminate\Database\Migrations\Migration as BaseMigration;
use App\Database\Migrations\Traits\HasDependencyChecks;

/**
 * Classe base customizada para migrations
 * Todas as migrations devem estender esta classe
 */
abstract class Migration extends BaseMigration
{
    use HasDependencyChecks;
    
    /**
     * Nome da tabela (pode ser definido na classe filha)
     */
    public string $table;

    /**
     * Obter nome da tabela
     */
    public function getTableName(): string
    {
        return $this->table ?? '';
    }
}







