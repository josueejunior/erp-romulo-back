<?php

namespace Tests\Unit\Application\Auth;

use Tests\TestCase;
use App\Application\Auth\UseCases\CriarUsuarioUseCase;
use App\Application\Auth\DTOs\CriarUsuarioDTO;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Services\UserRoleServiceInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Domain\Auth\Entities\User;
use App\Domain\Empresa\Entities\Empresa;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * Testes unitários para CriarUsuarioUseCase
 * 
 * Testa a refatoração onde:
 * - Repository apenas persiste User
 * - UseCase orquestra role e empresa
 * - Transação garante atomicidade
 */
class CriarUsuarioUseCaseTest extends TestCase
{
    private CriarUsuarioUseCase $useCase;
    private $userRepositoryMock;
    private $empresaRepositoryMock;
    private $roleServiceMock;
    private $eventDispatcherMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mocks
        $this->userRepositoryMock = Mockery::mock(UserRepositoryInterface::class);
        $this->empresaRepositoryMock = Mockery::mock(EmpresaRepositoryInterface::class);
        $this->roleServiceMock = Mockery::mock(UserRoleServiceInterface::class);
        $this->eventDispatcherMock = Mockery::mock(EventDispatcherInterface::class);
        
        // Bind mocks no container
        $this->app->instance(UserRepositoryInterface::class, $this->userRepositoryMock);
        $this->app->instance(EmpresaRepositoryInterface::class, $this->empresaRepositoryMock);
        $this->app->instance(UserRoleServiceInterface::class, $this->roleServiceMock);
        $this->app->instance(EventDispatcherInterface::class, $this->eventDispatcherMock);
        
        // Criar instância do UseCase
        $this->useCase = new CriarUsuarioUseCase(
            $this->userRepositoryMock,
            $this->empresaRepositoryMock,
            $this->roleServiceMock,
            $this->eventDispatcherMock
        );
    }

    /**
     * Testa criação de usuário com sucesso
     * Valida que o Repository apenas persiste, e o UseCase orquestra role e empresa
     */
    public function test_deve_criar_usuario_com_sucesso(): void
    {
        // Arrange
        $dto = new CriarUsuarioDTO(
            nome: 'João Silva',
            email: 'joao@example.com',
            senha: 'Senha123!@#',
            empresaId: 1,
            role: 'Administrador',
            empresas: null
        );
        
        $context = TenantContext::create(1);
        
        $empresa = new Empresa(
            id: 1,
            razaoSocial: 'Empresa Teste',
            cnpj: '12345678000190',
            tenantId: 1
        );
        
        $userCriado = new User(
            id: 1,
            tenantId: 1,
            nome: 'João Silva',
            email: 'joao@example.com',
            senhaHash: 'hashed_password',
            empresaAtivaId: 1
        );
        
        // Expectations
        $this->userRepositoryMock
            ->shouldReceive('emailExiste')
            ->once()
            ->with('joao@example.com')
            ->andReturn(false);
        
        $this->empresaRepositoryMock
            ->shouldReceive('buscarPorId')
            ->once()
            ->with(1)
            ->andReturn($empresa);
        
        // Mock transação
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });
        
        // Repository apenas persiste (sem role, sem empresa)
        $this->userRepositoryMock
            ->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(function ($user) {
                return $user instanceof User 
                    && $user->nome === 'João Silva'
                    && $user->email === 'joao@example.com';
            }))
            ->andReturn($userCriado);
        
        // UseCase orquestra role
        $this->roleServiceMock
            ->shouldReceive('atribuirRole')
            ->once()
            ->with($userCriado, 'Administrador');
        
        // UseCase orquestra vínculo com empresa
        $this->userRepositoryMock
            ->shouldReceive('vincularUsuarioEmpresa')
            ->once()
            ->with(1, 1, 'Administrador');
        
        // UseCase dispara evento
        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(\App\Domain\Auth\Events\UsuarioCriado::class));
        
        // Act
        $resultado = $this->useCase->executar($dto, $context);
        
        // Assert
        $this->assertInstanceOf(User::class, $resultado);
        $this->assertEquals(1, $resultado->id);
        $this->assertEquals('João Silva', $resultado->nome);
        $this->assertEquals('joao@example.com', $resultado->email);
    }

    /**
     * Testa criação com múltiplas empresas
     */
    public function test_deve_criar_usuario_com_multiplas_empresas(): void
    {
        // Arrange
        $dto = new CriarUsuarioDTO(
            nome: 'Maria Santos',
            email: 'maria@example.com',
            senha: 'Senha123!@#',
            empresaId: 1,
            role: 'Operacional',
            empresas: [1, 2, 3]
        );
        
        $context = TenantContext::create(1);
        
        $empresa1 = new Empresa(id: 1, razaoSocial: 'Empresa 1', cnpj: '11111111000111', tenantId: 1);
        $empresa2 = new Empresa(id: 2, razaoSocial: 'Empresa 2', cnpj: '22222222000122', tenantId: 1);
        $empresa3 = new Empresa(id: 3, razaoSocial: 'Empresa 3', cnpj: '33333333000133', tenantId: 1);
        
        $userCriado = new User(
            id: 2,
            tenantId: 1,
            nome: 'Maria Santos',
            email: 'maria@example.com',
            senhaHash: 'hashed_password',
            empresaAtivaId: 1
        );
        
        // Expectations
        $this->userRepositoryMock
            ->shouldReceive('emailExiste')
            ->once()
            ->andReturn(false);
        
        $this->empresaRepositoryMock
            ->shouldReceive('buscarPorId')
            ->with(1)
            ->once()
            ->andReturn($empresa1);
        
        $this->empresaRepositoryMock
            ->shouldReceive('buscarPorId')
            ->with(2)
            ->once()
            ->andReturn($empresa2);
        
        $this->empresaRepositoryMock
            ->shouldReceive('buscarPorId')
            ->with(3)
            ->once()
            ->andReturn($empresa3);
        
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });
        
        $this->userRepositoryMock
            ->shouldReceive('criar')
            ->once()
            ->andReturn($userCriado);
        
        $this->roleServiceMock
            ->shouldReceive('atribuirRole')
            ->once();
        
        // Deve vincular a 3 empresas (1, 2, 3)
        $this->userRepositoryMock
            ->shouldReceive('vincularUsuarioEmpresa')
            ->times(3)
            ->with(Mockery::any(), Mockery::anyOf(1, 2, 3), 'Operacional');
        
        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once();
        
        // Act
        $resultado = $this->useCase->executar($dto, $context);
        
        // Assert
        $this->assertInstanceOf(User::class, $resultado);
    }

    /**
     * Testa que lança exceção quando email já existe
     */
    public function test_deve_lancar_excecao_quando_email_ja_existe(): void
    {
        // Arrange
        $dto = new CriarUsuarioDTO(
            nome: 'João Silva',
            email: 'joao@example.com',
            senha: 'Senha123!@#',
            empresaId: 1,
            role: 'Administrador',
            empresas: null
        );
        
        $context = TenantContext::create(1);
        
        // Expectations
        $this->userRepositoryMock
            ->shouldReceive('emailExiste')
            ->once()
            ->with('joao@example.com')
            ->andReturn(true);
        
        // Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Este e-mail já está cadastrado.');
        
        $this->useCase->executar($dto, $context);
    }

    /**
     * Testa que lança exceção quando empresa não existe
     */
    public function test_deve_lancar_excecao_quando_empresa_nao_existe(): void
    {
        // Arrange
        $dto = new CriarUsuarioDTO(
            nome: 'João Silva',
            email: 'joao@example.com',
            senha: 'Senha123!@#',
            empresaId: 999,
            role: 'Administrador',
            empresas: null
        );
        
        $context = TenantContext::create(1);
        
        // Expectations
        $this->userRepositoryMock
            ->shouldReceive('emailExiste')
            ->once()
            ->andReturn(false);
        
        $this->empresaRepositoryMock
            ->shouldReceive('buscarPorId')
            ->once()
            ->with(999)
            ->andReturn(null);
        
        // Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Empresa não encontrada neste tenant.');
        
        $this->useCase->executar($dto, $context);
    }

    /**
     * Testa que lança exceção quando empresa adicional não existe
     */
    public function test_deve_lancar_excecao_quando_empresa_adicional_nao_existe(): void
    {
        // Arrange
        $dto = new CriarUsuarioDTO(
            nome: 'João Silva',
            email: 'joao@example.com',
            senha: 'Senha123!@#',
            empresaId: 1,
            role: 'Administrador',
            empresas: [1, 999] // Empresa 999 não existe
        );
        
        $context = TenantContext::create(1);
        
        $empresa1 = new Empresa(id: 1, razaoSocial: 'Empresa 1', cnpj: '11111111000111', tenantId: 1);
        
        // Expectations
        $this->userRepositoryMock
            ->shouldReceive('emailExiste')
            ->once()
            ->andReturn(false);
        
        $this->empresaRepositoryMock
            ->shouldReceive('buscarPorId')
            ->with(1)
            ->twice() // Uma vez para empresa principal, outra para validação
            ->andReturn($empresa1);
        
        $this->empresaRepositoryMock
            ->shouldReceive('buscarPorId')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });
        
        $userCriado = new User(
            id: 1,
            tenantId: 1,
            nome: 'João Silva',
            email: 'joao@example.com',
            senhaHash: 'hashed_password',
            empresaAtivaId: 1
        );
        
        $this->userRepositoryMock
            ->shouldReceive('criar')
            ->once()
            ->andReturn($userCriado);
        
        $this->roleServiceMock
            ->shouldReceive('atribuirRole')
            ->once();
        
        // Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Empresa ID 999 não encontrada neste tenant.');
        
        $this->useCase->executar($dto, $context);
    }

    /**
     * Testa que transação garante atomicidade (rollback em caso de erro)
     */
    public function test_deve_fazer_rollback_em_caso_de_erro_na_transacao(): void
    {
        // Arrange
        $dto = new CriarUsuarioDTO(
            nome: 'João Silva',
            email: 'joao@example.com',
            senha: 'Senha123!@#',
            empresaId: 1,
            role: 'Administrador',
            empresas: null
        );
        
        $context = TenantContext::create(1);
        
        $empresa = new Empresa(id: 1, razaoSocial: 'Empresa 1', cnpj: '11111111000111', tenantId: 1);
        
        // Expectations
        $this->userRepositoryMock
            ->shouldReceive('emailExiste')
            ->once()
            ->andReturn(false);
        
        $this->empresaRepositoryMock
            ->shouldReceive('buscarPorId')
            ->once()
            ->andReturn($empresa);
        
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                // Simular erro ao atribuir role
                $this->roleServiceMock
                    ->shouldReceive('atribuirRole')
                    ->once()
                    ->andThrow(new \Exception('Erro ao atribuir role'));
                
                return $callback();
            });
        
        $userCriado = new User(
            id: 1,
            tenantId: 1,
            nome: 'João Silva',
            email: 'joao@example.com',
            senhaHash: 'hashed_password',
            empresaAtivaId: 1
        );
        
        $this->userRepositoryMock
            ->shouldReceive('criar')
            ->once()
            ->andReturn($userCriado);
        
        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Erro ao atribuir role');
        
        $this->useCase->executar($dto, $context);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

