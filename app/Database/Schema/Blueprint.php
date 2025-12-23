<?php

namespace App\Database\Schema;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;

/**
 * Blueprint customizado com métodos auxiliares para migrations
 * Facilita criação de campos comuns e foreign keys padronizadas
 */
class Blueprint extends BaseBlueprint
{
    // Timestamps customizados (em português)
    public const string CREATED_AT = 'criado_em';
    public const string UPDATED_AT = 'atualizado_em';
    public const string DELETED_AT = 'excluido_em';

    // Constantes para tamanhos de strings (padronização)
    public const int VARCHAR_TINY = 50;
    public const int VARCHAR_SMALL = 100;
    public const int VARCHAR_DEFAULT = 250;
    public const int VARCHAR_MEDIUM = 1000;
    public const int VARCHAR_LARGE = 2500;
    public const int VARCHAR_EXTRA_LARGE = 5000;

    /**
     * Criar foreign key para empresa
     * Cria: empresa_id -> empresas
     * Aplica automaticamente após o campo id
     */
    public function foreignEmpresa(bool $nullable = false): \Illuminate\Database\Schema\ColumnDefinition
    {
        $column = $this->foreignId('empresa_id')->after('id');
        
        if ($nullable) {
            $column->nullable();
        }
        
        return $column->constrained('empresas')->onDelete('cascade');
    }

    /**
     * Criar foreign key para empresa ativa (usuários)
     * Cria: empresa_ativa_id -> empresas (nullable, set null on delete)
     */
    public function foreignEmpresaAtiva()
    {
        return $this->foreignId('empresa_ativa_id')
            ->nullable()
            ->constrained('empresas')
            ->onDelete('set null');
    }

    /**
     * Aplicar empresa_id em tabela existente (para migrations de alteração)
     * Verifica se a coluna já existe antes de adicionar
     */
    public function addEmpresaIdIfNotExists(bool $nullable = false): void
    {
        if (!$this->hasColumn('empresa_id')) {
            $column = $this->foreignId('empresa_id')->after('id');
            
            if ($nullable) {
                $column->nullable();
            }
            
            $column->constrained('empresas')->onDelete('cascade');
        }
    }

    /**
     * Criar foreign key para tenant
     * Cria: tenant_id -> tenants
     */
    public function foreignTenant(bool $nullable = false): \Illuminate\Database\Schema\ColumnDefinition
    {
        $column = $this->foreignId('tenant_id');
        
        if ($nullable) {
            $column->nullable();
        }
        
        return $column->constrained('tenants')->onDelete('cascade');
    }

    /**
     * Criar foreign key para usuário (padrão do sistema)
     * Cria: usuario_id -> users
     * Use este método para tabelas customizadas do sistema
     */
    public function foreignUsuario(bool $nullable = false): \Illuminate\Database\Schema\ColumnDefinition
    {
        $column = $this->foreignId('usuario_id');
        
        if ($nullable) {
            $column->nullable();
        }
        
        return $column->constrained('users')->onDelete('set null');
    }

    /**
     * Criar foreign key para user_id (padrão Laravel)
     * Cria: user_id -> users
     * Use este método para tabelas padrão do Laravel (sessions, etc)
     */
    public function foreignUserId(bool $nullable = false): \Illuminate\Database\Schema\ColumnDefinition
    {
        $column = $this->foreignId('user_id');
        
        if ($nullable) {
            $column->nullable();
        }
        
        return $column->constrained('users')->onDelete('cascade');
    }

    /**
     * Criar foreign key para pessoa
     * Cria: pessoa_id -> pessoas
     */
    public function foreignPessoa(bool $nullable = false): \Illuminate\Database\Schema\ColumnDefinition
    {
        $column = $this->foreignId('pessoa_id');
        
        if ($nullable) {
            $column->nullable();
        }
        
        return $column->constrained('pessoas')->onDelete('set null');
    }

    /**
     * Criar foreign key genérica
     * @param string $column Nome da coluna
     * @param string $table Nome da tabela referenciada
     * @param bool $nullable Se a coluna pode ser nula
     * @param string $onDelete Ação ao deletar (cascade, set null, restrict)
     */
    public function foreignIdCustom(
        string $column,
        string $table,
        bool $nullable = false,
        string $onDelete = 'restrict'
    ): \Illuminate\Database\Schema\ColumnDefinition {
        $foreignColumn = $this->foreignId($column);
        
        if ($nullable) {
            $foreignColumn->nullable();
        }
        
        $constraint = $foreignColumn->constrained($table);
        
        if ($onDelete === 'cascade') {
            $constraint->onDelete('cascade');
        } elseif ($onDelete === 'set null') {
            $constraint->onDelete('set null');
        } else {
            $constraint->onDelete('restrict');
        }
        
        return $foreignColumn;
    }

    /**
     * Criar campos de endereço completo
     * Cria: cep, logradouro, numero, bairro, complemento, cidade, estado
     */
    public function endereco(): void
    {
        $this->string('cep', 10)->nullable();
        $this->string('logradouro', self::VARCHAR_DEFAULT)->nullable();
        $this->string('numero', 20)->nullable();
        $this->string('bairro', self::VARCHAR_SMALL)->nullable();
        $this->string('complemento', self::VARCHAR_SMALL)->nullable();
        $this->string('cidade', self::VARCHAR_SMALL)->nullable();
        $this->string('estado', 2)->nullable();
    }

    /**
     * Criar campos de coordenadas geográficas
     * Cria: latitude, longitude
     */
    public function coordenadas(): void
    {
        $this->decimal('latitude', 10, 8)->nullable();
        $this->decimal('longitude', 11, 8)->nullable();
    }

    /**
     * Criar campo de email padronizado
     * String VARCHAR_DEFAULT para email
     */
    public function email(string $column = 'email', bool $nullable = true): \Illuminate\Database\Schema\ColumnDefinition
    {
        $emailColumn = $this->string($column, self::VARCHAR_DEFAULT);
        
        if ($nullable) {
            $emailColumn->nullable();
        }
        
        return $emailColumn;
    }

    /**
     * Criar campo de telefone padronizado
     * String 15 caracteres
     */
    public function telefone(string $column = 'telefone', bool $nullable = true): \Illuminate\Database\Schema\ColumnDefinition
    {
        $telefoneColumn = $this->string($column, 15);
        
        if ($nullable) {
            $telefoneColumn->nullable();
        }
        
        return $telefoneColumn;
    }

    /**
     * Criar campo de descrição padronizado
     * String VARCHAR_DEFAULT
     */
    public function descricao(string $column = 'descricao', bool $nullable = true): \Illuminate\Database\Schema\ColumnDefinition
    {
        $descricaoColumn = $this->string($column, self::VARCHAR_DEFAULT);
        
        if ($nullable) {
            $descricaoColumn->nullable();
        }
        
        return $descricaoColumn;
    }

    /**
     * Criar campo de observação padronizado
     * String VARCHAR_MEDIUM
     */
    public function observacao(string $column = 'observacao', bool $nullable = true): \Illuminate\Database\Schema\ColumnDefinition
    {
        $observacaoColumn = $this->text($column);
        
        if ($nullable) {
            $observacaoColumn->nullable();
        }
        
        return $observacaoColumn;
    }

    /**
     * Criar campo ativo/inativo padronizado
     * Boolean com default true
     */
    public function ativo(string $column = 'ativo'): \Illuminate\Database\Schema\ColumnDefinition
    {
        return $this->boolean($column)->default(true);
    }

    /**
     * Criar timestamps em português
     * Cria: criado_em, atualizado_em
     * 
     * @param int|null $precision Precisão (ignorado, mantido para compatibilidade com Laravel)
     */
    public function datetimes($precision = null): void
    {
        $this->timestamp(self::CREATED_AT)->nullable();
        $this->timestamp(self::UPDATED_AT)->nullable();
    }

    /**
     * Criar timestamps com soft deletes em português
     * Cria: criado_em, atualizado_em, excluido_em
     */
    public function datetimesWithSoftDeletes(): void
    {
        $this->datetimes();
        $this->timestamp(self::DELETED_AT)->nullable();
    }

    /**
     * Criar campo de status padronizado
     * Enum com valores comuns
     * @param array $values Valores do enum
     * @param string|null $default Valor padrão (null = sem default)
     * @param string $column Nome da coluna (padrão: 'status')
     */
    public function status(array $values = ['ativo', 'inativo'], ?string $default = 'ativo', string $column = 'status'): \Illuminate\Database\Schema\ColumnDefinition
    {
        $enumColumn = $this->enum($column, $values);
        
        if ($default !== null) {
            $enumColumn->default($default);
        }
        
        return $enumColumn;
    }
}

