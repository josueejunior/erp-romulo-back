<?php

namespace App\Http\Controllers\Api\Schemas;

/**
 * Schemas OpenAPI para modelos do sistema
 * 
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     required={"id", "name", "email"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="João Silva"),
 *     @OA\Property(property="email", type="string", format="email", example="joao@empresa.com"),
 *     @OA\Property(property="empresa_ativa_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Processo",
 *     type="object",
 *     required={"id", "empresa_id", "modalidade", "status"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="empresa_id", type="integer", example=1),
 *     @OA\Property(property="orgao_id", type="integer", example=1),
 *     @OA\Property(property="modalidade", type="string", enum={"pregão", "concorrência", "tomada_preco", "convite", "dispensa", "inexigibilidade"}, example="pregão"),
 *     @OA\Property(property="numero_modalidade", type="string", example="001/2026"),
 *     @OA\Property(property="objeto_resumido", type="string", example="Aquisição de materiais de escritório"),
 *     @OA\Property(property="status", type="string", enum={"rascunho", "participacao", "julgamento_habilitacao", "execucao", "pagamento", "encerramento"}, example="participacao"),
 *     @OA\Property(property="data_hora_sessao_publica", type="string", format="date-time"),
 *     @OA\Property(property="portal", type="string", example="ComprasNet"),
 *     @OA\Property(property="srp", type="boolean", example=false)
 * )
 * 
 * @OA\Schema(
 *     schema="Fornecedor",
 *     type="object",
 *     required={"id", "empresa_id", "razao_social"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="empresa_id", type="integer", example=1),
 *     @OA\Property(property="razao_social", type="string", example="Fornecedor Exemplo LTDA"),
 *     @OA\Property(property="nome_fantasia", type="string", example="Fornecedor Exemplo"),
 *     @OA\Property(property="cnpj", type="string", example="12.345.678/0001-90"),
 *     @OA\Property(property="email", type="string", format="email", example="contato@fornecedor.com"),
 *     @OA\Property(property="telefone", type="string", example="(11) 98765-4321"),
 *     @OA\Property(property="cidade", type="string", example="São Paulo"),
 *     @OA\Property(property="estado", type="string", example="SP"),
 *     @OA\Property(property="is_transportadora", type="boolean", example=false)
 * )
 * 
 * @OA\Schema(
 *     schema="Contrato",
 *     type="object",
 *     required={"id", "processo_id", "numero", "valor_total"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="processo_id", type="integer", example=1),
 *     @OA\Property(property="numero", type="string", example="CT-001/2026"),
 *     @OA\Property(property="valor_total", type="number", format="float", example=150000.00),
 *     @OA\Property(property="data_assinatura", type="string", format="date"),
 *     @OA\Property(property="data_vigencia_inicio", type="string", format="date"),
 *     @OA\Property(property="data_vigencia_fim", type="string", format="date"),
 *     @OA\Property(property="status", type="string", enum={"ativo", "suspenso", "encerrado"}, example="ativo")
 * )
 * 
 * @OA\Schema(
 *     schema="Assinatura",
 *     type="object",
 *     required={"id", "user_id", "plano_id", "status"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="tenant_id", type="integer", example=1),
 *     @OA\Property(property="plano_id", type="integer", example=2),
 *     @OA\Property(property="status", type="string", enum={"ativa", "suspensa", "cancelada", "expirada", "pendente"}, example="ativa"),
 *     @OA\Property(property="data_inicio", type="string", format="date"),
 *     @OA\Property(property="data_fim", type="string", format="date"),
 *     @OA\Property(property="valor_pago", type="number", format="float", example=99.90),
 *     @OA\Property(property="metodo_pagamento", type="string", example="pix")
 * )
 * 
 * @OA\Schema(
 *     schema="Plano",
 *     type="object",
 *     required={"id", "nome", "valor_mensal"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="nome", type="string", example="Profissional"),
 *     @OA\Property(property="slug", type="string", example="profissional"),
 *     @OA\Property(property="descricao", type="string", example="Plano ideal para empresas em crescimento"),
 *     @OA\Property(property="valor_mensal", type="number", format="float", example=149.90),
 *     @OA\Property(property="valor_anual", type="number", format="float", example=1499.00),
 *     @OA\Property(property="max_processos", type="integer", example=100),
 *     @OA\Property(property="max_usuarios", type="integer", example=5),
 *     @OA\Property(property="recursos", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="ativo", type="boolean", example=true)
 * )
 * 
 * @OA\Schema(
 *     schema="Empresa",
 *     type="object",
 *     required={"id", "nome", "cnpj"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="nome", type="string", example="Empresa Exemplo LTDA"),
 *     @OA\Property(property="cnpj", type="string", example="12.345.678/0001-90"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="telefone", type="string"),
 *     @OA\Property(property="endereco", type="string"),
 *     @OA\Property(property="cidade", type="string"),
 *     @OA\Property(property="estado", type="string"),
 *     @OA\Property(property="cep", type="string")
 * )
 * 
 * @OA\Schema(
 *     schema="Dashboard",
 *     type="object",
 *     @OA\Property(property="total_processos", type="integer", example=50),
 *     @OA\Property(property="processos_ativos", type="integer", example=25),
 *     @OA\Property(property="valor_total_contratos", type="number", format="float", example=1500000.00),
 *     @OA\Property(property="alertas_pendentes", type="integer", example=3),
 *     @OA\Property(property="processos_por_status", type="object",
 *         @OA\Property(property="participacao", type="integer", example=10),
 *         @OA\Property(property="julgamento", type="integer", example=5),
 *         @OA\Property(property="execucao", type="integer", example=8),
 *         @OA\Property(property="encerrados", type="integer", example=2)
 *     )
 * )
 */
class ModelSchemas
{
    // Esta classe serve apenas para anotações OpenAPI dos modelos
}

