<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="ERP Licitações API",
 *     description="API RESTful para o sistema ERP de Licitações - Gestão completa de processos licitatórios",
 *     @OA\Contact(
 *         email="suporte@addireta.com",
 *         name="Suporte Técnico"
 *     ),
 *     @OA\License(
 *         name="Proprietário",
 *         url="https://addireta.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="/api/v1",
 *     description="API v1"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Autenticação via JWT Token. Use o endpoint /auth/login para obter o token."
 * )
 * 
 * @OA\Tag(
 *     name="Autenticação",
 *     description="Endpoints de autenticação e gerenciamento de sessão"
 * )
 * 
 * @OA\Tag(
 *     name="Processos",
 *     description="Gerenciamento de processos licitatórios"
 * )
 * 
 * @OA\Tag(
 *     name="Fornecedores",
 *     description="Cadastro e gerenciamento de fornecedores"
 * )
 * 
 * @OA\Tag(
 *     name="Contratos",
 *     description="Gerenciamento de contratos"
 * )
 * 
 * @OA\Tag(
 *     name="Assinaturas",
 *     description="Gerenciamento de planos e assinaturas"
 * )
 * 
 * @OA\Tag(
 *     name="Dashboard",
 *     description="Métricas e indicadores"
 * )
 * 
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     required={"message"},
 *     @OA\Property(property="message", type="string", example="Erro de validação"),
 *     @OA\Property(property="errors", type="object", additionalProperties={"type":"array","items":{"type":"string"}})
 * )
 * 
 * @OA\Schema(
 *     schema="Pagination",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=100),
 *     @OA\Property(property="last_page", type="integer", example=7)
 * )
 */
class ApiDocumentation
{
    // Esta classe serve apenas para anotações do Swagger
}

