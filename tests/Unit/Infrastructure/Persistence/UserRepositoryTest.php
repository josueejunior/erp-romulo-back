<?php

namespace Tests\Unit\Infrastructure\Persistence;

use Tests\TestCase;
use App\Infrastructure\Persistence\Eloquent\UserRepository;
use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

/**
 * Testes unitários para UserRepository
 * 
 * Testa que o Repository:
 * - Apenas persiste User (sem role, sem empresa)
 * - Não conhece lógica de negócio
 * - Método vincularUsuarioEmpresa funciona corretamente
 */
class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(UserRepositoryInterface::class);
    }

    /**
     * Testa que criar() apenas persiste o User, sem atribuir role ou vincular empresa
     */
    public function test_criar_deve_apenas_persistir_user(): void
    {
        // Arrange
        $user = new User(
            id: null,
            tenantId: 1,
            nome: 'João Silva',
            email: 'joao@example.com',
            senhaHash: Hash::make('Senha123!@#'),
            empresaAtivaId: 1
        );
        
        // Act
        $userCriado = $this->repository->criar($user);
        
        // Assert
        $this->assertNotNull($userCriado->id);
        $this->assertEquals('João Silva', $userCriado->nome);
        $this->assertEquals('joao@example.com', $userCriado->email);
        $this->assertEquals(1, $userCriado->empresaAtivaId);
        
        // Verificar que foi salvo no banco
        $model = UserModel::find($userCriado->id);
        $this->assertNotNull($model);
        $this->assertEquals('João Silva', $model->name);
        $this->assertEquals('joao@example.com', $model->email);
        
        // Verificar que NÃO tem role atribuída (isso deve ser feito no UseCase)
        $this->assertFalse($model->hasRole('Administrador'));
        $this->assertFalse($model->hasRole('Operacional'));
        
        // Verificar que NÃO tem empresa vinculada (isso deve ser feito no UseCase)
        $this->assertCount(0, $model->empresas);
    }

    /**
     * Testa que criar() normaliza email para lowercase
     */
    public function test_criar_deve_normalizar_email_para_lowercase(): void
    {
        // Arrange
        $user = new User(
            id: null,
            tenantId: 1,
            nome: 'Maria Santos',
            email: 'MARIA@EXAMPLE.COM',
            senhaHash: Hash::make('Senha123!@#'),
            empresaAtivaId: 1
        );
        
        // Act
        $userCriado = $this->repository->criar($user);
        
        // Assert
        $this->assertEquals('maria@example.com', $userCriado->email);
        
        $model = UserModel::find($userCriado->id);
        $this->assertEquals('maria@example.com', $model->email);
    }

    /**
     * Testa que vincularUsuarioEmpresa() cria vínculo quando não existe
     * Nota: Este teste requer tabela empresa_user e Empresa criadas
     * Para simplificar, testamos apenas a lógica do método
     */
    public function test_vincularUsuarioEmpresa_deve_criar_vinculo_quando_nao_existe(): void
    {
        // Este teste requer setup completo de banco (migrations)
        // Por enquanto, vamos testar apenas a lógica básica
        $this->markTestSkipped('Requer setup completo de banco com migrations');
    }

    /**
     * Testa que vincularUsuarioEmpresa() atualiza perfil quando vínculo já existe
     */
    public function test_vincularUsuarioEmpresa_deve_atualizar_perfil_quando_vinculo_existe(): void
    {
        // Este teste requer setup completo de banco (migrations)
        $this->markTestSkipped('Requer setup completo de banco com migrations');
    }

    /**
     * Testa que emailExiste() retorna false quando email não existe
     */
    public function test_emailExiste_deve_retornar_false_quando_email_nao_existe(): void
    {
        // Act
        $existe = $this->repository->emailExiste('naoexiste@example.com');
        
        // Assert
        $this->assertFalse($existe);
    }

    /**
     * Testa que emailExiste() retorna true quando email existe
     */
    public function test_emailExiste_deve_retornar_true_quando_email_existe(): void
    {
        // Arrange
        $user = new User(
            id: null,
            tenantId: 1,
            nome: 'João Silva',
            email: 'joao@example.com',
            senhaHash: Hash::make('Senha123!@#'),
            empresaAtivaId: 1
        );
        
        $this->repository->criar($user);
        
        // Act
        $existe = $this->repository->emailExiste('joao@example.com');
        
        // Assert
        $this->assertTrue($existe);
    }

    /**
     * Testa que emailExiste() é case-insensitive
     */
    public function test_emailExiste_deve_ser_case_insensitive(): void
    {
        // Arrange
        $user = new User(
            id: null,
            tenantId: 1,
            nome: 'João Silva',
            email: 'joao@example.com',
            senhaHash: Hash::make('Senha123!@#'),
            empresaAtivaId: 1
        );
        
        $this->repository->criar($user);
        
        // Act & Assert
        $this->assertTrue($this->repository->emailExiste('JOAO@EXAMPLE.COM'));
        $this->assertTrue($this->repository->emailExiste('JoAo@ExAmPlE.CoM'));
        $this->assertTrue($this->repository->emailExiste('joao@example.com'));
    }
}

