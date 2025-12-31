# TESTES - API Processo Licitatório (Curto Prazo)

## Requisitos
- PHP 8.1+
- Laravel 10+
- Banco de dados configurado
- Auth middleware funcionando

## Executar Testes

```bash
# Todos os testes
php artisan test

# Apenas testes do módulo Orcamento
php artisan test --filter OrcamentoTest

# Apenas testes do módulo Processo
php artisan test --filter ProcessoItemTest

# Com output verbose
php artisan test -v

# Com cobertura de código
php artisan test --coverage
```

## Testes Unitários

### OrcamentoService
```php
// tests/Unit/Modules/Orcamento/Services/OrcamentoServiceTest.php

use Tests\TestCase;
use App\Modules\Orcamento\Services\OrcamentoService;
use App\Modules\Processo\Models\Processo;
use App\Modules\Empresa\Models\Empresa;

class OrcamentoServiceTest extends TestCase
{
    protected OrcamentoService $service;
    protected Empresa $empresa;
    protected Processo $processo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrcamentoService::class);
        $this->empresa = Empresa::factory()->create();
        $this->processo = Processo::factory()->create(['empresa_id' => $this->empresa->id]);
    }

    public function test_pode_criar_orcamento()
    {
        $orcamento = $this->service->salvar(
            $this->processo->id,
            1, // fornecedor_id
            [
                [
                    'processo_item_id' => 1,
                    'quantidade' => 10,
                    'preco_unitario' => 100,
                    'especificacoes' => 'Especificação do item'
                ]
            ],
            $this->empresa->id
        );

        $this->assertNotNull($orcamento);
        $this->assertEquals($this->processo->id, $orcamento->processo_id);
    }

    public function test_calcula_total_orcamento()
    {
        $orcamento = $this->service->salvar(
            $this->processo->id,
            1,
            [
                [
                    'processo_item_id' => 1,
                    'quantidade' => 10,
                    'preco_unitario' => 100,
                    'especificacoes' => null
                ]
            ],
            $this->empresa->id
        );

        $this->assertEquals(1000, $orcamento->total);
    }

    public function test_validacao_empresa_orcamento()
    {
        $this->expectException(\Exception::class);
        
        $this->service->validarOrcamentoEmpresa($orcamento, 999);
    }
}
```

### FormacaoPrecoService
```php
// tests/Unit/Modules/Orcamento/Services/FormacaoPrecoServiceTest.php

use Tests\TestCase;
use App\Modules\Orcamento\Services\FormacaoPrecoService;
use App\Modules\Orcamento\Models\OrcamentoItem;

class FormacaoPrecoServiceTest extends TestCase
{
    protected FormacaoPrecoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FormacaoPrecoService::class);
    }

    public function test_calcula_preco_minimo_venda()
    {
        $dados = [
            'orcamento_item_id' => 1,
            'custo_produto' => 100,
            'frete' => 10,
            'impostos_percentual' => 10,
            'margem_lucro_percentual' => 20,
        ];

        $formacao = $this->service->salvar($dados);

        // (100 + 10) * (1 + 0.10) / (1 - 0.20) = 110 * 1.10 / 0.80 = 151.25
        $this->assertEquals(151.25, $formacao->preco_minimo);
    }

    public function test_validacao_impostos_percentual()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->salvar([
            'orcamento_item_id' => 1,
            'custo_produto' => 100,
            'frete' => 10,
            'impostos_percentual' => 150, // Inválido
            'margem_lucro_percentual' => 20,
        ]);
    }

    public function test_validacao_margem_percentual()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->salvar([
            'orcamento_item_id' => 1,
            'custo_produto' => 100,
            'frete' => 10,
            'impostos_percentual' => 10,
            'margem_lucro_percentual' => 150, // Inválido
        ]);
    }
}
```

## Testes de Integração (API)

### Orçamento API
```php
// tests/Feature/Modules/Orcamento/OrcamentoApiTest.php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Empresa\Models\Empresa;
use App\Modules\Processo\Models\Processo;

class OrcamentoApiTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;
    protected Processo $processo;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->processo = Processo::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->user = \App\Models\User::factory()->create();
    }

    public function test_lista_orcamentos_do_processo()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/processos/{$this->processo->id}/orcamentos");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'count', 'message']);
    }

    public function test_cria_novo_orcamento()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/processos/{$this->processo->id}/orcamentos", [
                'fornecedor_id' => 1,
                'itens' => [
                    [
                        'processo_item_id' => 1,
                        'quantidade' => 10,
                        'preco_unitario' => 100,
                        'especificacoes' => 'Item teste'
                    ]
                ]
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id', 'processo_id', 'fornecedor_id']]);
    }

    public function test_validacao_fornecedor_id_obrigatorio()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/processos/{$this->processo->id}/orcamentos", [
                'itens' => [[
                    'processo_item_id' => 1,
                    'quantidade' => 10,
                    'preco_unitario' => 100,
                ]]
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('fornecedor_id');
    }

    public function test_obtem_orcamento_especifico()
    {
        $orcamento = $this->processo->orcamentos()->create([
            'fornecedor_id' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/orcamentos/{$orcamento->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $orcamento->id);
    }

    public function test_atualiza_orcamento()
    {
        $orcamento = $this->processo->orcamentos()->create([
            'fornecedor_id' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/orcamentos/{$orcamento->id}", [
                'itens' => [
                    [
                        'processo_item_id' => 1,
                        'quantidade' => 20,
                        'preco_unitario' => 200,
                    ]
                ]
            ]);

        $response->assertStatus(200);
    }

    public function test_deleta_orcamento()
    {
        $orcamento = $this->processo->orcamentos()->create([
            'fornecedor_id' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/orcamentos/{$orcamento->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('orcamentos', ['id' => $orcamento->id]);
    }
}
```

### Formação de Preço API
```php
// tests/Feature/Modules/Orcamento/FormacaoPrecoApiTest.php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Empresa\Models\Empresa;
use App\Modules\Processo\Models\Processo;

class FormacaoPrecoApiTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;
    protected Processo $processo;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->processo = Processo::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->user = \App\Models\User::factory()->create();
    }

    public function test_lista_formacao_preco()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/processos/{$this->processo->id}/formacao-preco");

        $response->assertStatus(200);
    }

    public function test_cria_formacao_preco()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/processos/{$this->processo->id}/formacao-preco", [
                'orcamento_item_id' => 1,
                'custo_produto' => 100,
                'frete' => 10,
                'impostos_percentual' => 10,
                'margem_lucro_percentual' => 20,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.preco_minimo', 151.25);
    }

    public function test_atualiza_formacao_preco()
    {
        // Criar orçamento e formação primeiro
        $orcamento = $this->processo->orcamentos()->create(['fornecedor_id' => 1]);
        $item = $orcamento->itens()->create([
            'processo_item_id' => 1,
            'quantidade' => 10,
            'preco_unitario' => 100,
        ]);
        $formacao = $item->formacaoPreco()->create([
            'custo_produto' => 100,
            'frete' => 10,
            'impostos_percentual' => 10,
            'margem_lucro_percentual' => 20,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/formacao-preco/{$formacao->id}", [
                'custo_produto' => 120,
                'frete' => 15,
            ]);

        $response->assertStatus(200);
    }
}
```

### ProcessoItem Disputa/Julgamento API
```php
// tests/Feature/Modules/Processo/ProcessoItemDisputaTest.php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Empresa\Models\Empresa;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;

class ProcessoItemDisputaTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;
    protected Processo $processo;
    protected ProcessoItem $item;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->processo = Processo::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->item = ProcessoItem::factory()->create(['processo_id' => $this->processo->id]);
        $this->user = \App\Models\User::factory()->create();
    }

    public function test_atualiza_valor_final_pos_disputa()
    {
        $response = $this->actingAs($this->user)
            ->patchJson(
                "/api/v1/processos/{$this->processo->id}/itens/{$this->item->id}/valor-final-disputa",
                ['valor_final_pos_disputa' => 850]
            );

        $response->assertStatus(200);
        $this->item->refresh();
        $this->assertEquals(850, $this->item->valor_final_pos_disputa);
    }

    public function test_atualiza_valor_negociado_pos_julgamento()
    {
        $response = $this->actingAs($this->user)
            ->patchJson(
                "/api/v1/processos/{$this->processo->id}/itens/{$this->item->id}/valor-negociado",
                ['valor_negociado_pos_julgamento' => 800]
            );

        $response->assertStatus(200);
        $this->item->refresh();
        $this->assertEquals(800, $this->item->valor_negociado_pos_julgamento);
    }

    public function test_atualiza_status_item()
    {
        $response = $this->actingAs($this->user)
            ->patchJson(
                "/api/v1/processos/{$this->processo->id}/itens/{$this->item->id}/status",
                ['status_item' => 'aceito_habilitado']
            );

        $response->assertStatus(200);
        $this->item->refresh();
        $this->assertEquals('aceito_habilitado', $this->item->status_item);
    }

    public function test_validacao_status_invalido()
    {
        $response = $this->actingAs($this->user)
            ->patchJson(
                "/api/v1/processos/{$this->processo->id}/itens/{$this->item->id}/status",
                ['status_item' => 'status_invalido']
            );

        $response->assertStatus(422);
    }

    public function test_validacao_valor_negativo()
    {
        $response = $this->actingAs($this->user)
            ->patchJson(
                "/api/v1/processos/{$this->processo->id}/itens/{$this->item->id}/valor-final-disputa",
                ['valor_final_pos_disputa' => -100]
            );

        $response->assertStatus(422);
    }
}
```

## Cenários de Teste Manual (Postman)

### 1. Criar Orçamento
```
POST /api/v1/processos/1/orcamentos
Authorization: Bearer {token}
Content-Type: application/json

{
  "fornecedor_id": 1,
  "itens": [
    {
      "processo_item_id": 1,
      "quantidade": 10,
      "preco_unitario": 100,
      "especificacoes": "Especificação do produto"
    },
    {
      "processo_item_id": 2,
      "quantidade": 5,
      "preco_unitario": 50,
      "especificacoes": null
    }
  ]
}

Resposta esperada: 201 Created
```

### 2. Criar Formação de Preço
```
POST /api/v1/processos/1/formacao-preco
Authorization: Bearer {token}
Content-Type: application/json

{
  "orcamento_item_id": 1,
  "custo_produto": 100,
  "frete": 10,
  "impostos_percentual": 10,
  "margem_lucro_percentual": 20,
  "observacoes": "Formação de preço inicial"
}

Resposta esperada: 201 Created
{
  "id": 1,
  "preco_minimo": 151.25,
  "preco_recomendado": 181.5,
  ...
}
```

### 3. Atualizar Valor Final Pós-Disputa
```
PATCH /api/v1/processos/1/itens/1/valor-final-disputa
Authorization: Bearer {token}
Content-Type: application/json

{
  "valor_final_pos_disputa": 850
}

Resposta esperada: 200 OK
```

### 4. Atualizar Status do Item
```
PATCH /api/v1/processos/1/itens/1/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status_item": "aceito_habilitado"
}

Resposta esperada: 200 OK
```

## Cobertura de Testes

- [ ] OrcamentoService: CRUD completo
- [ ] FormacaoPrecoService: Cálculos e validações
- [ ] ProcessoItemController: Disputas e julgamentos
- [ ] Permissões e autorizações
- [ ] Validações de entrada
- [ ] Transições de status automáticas
- [ ] Relacionamentos entre modelos

---

**Última atualização:** 31/12/2025
