<?php

namespace App\Services;

use App\Models\Processo;
use App\Models\ProcessoItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;

class ExportacaoService
{
    /**
     * Gera proposta comercial em PDF
     */
    public function gerarPropostaComercial(Processo $processo): string
    {
        $processo->load([
            'orgao',
            'setor',
            'itens' => function ($query) {
                $query->orderBy('numero_item');
            },
            'itens.orcamentos' => function ($query) {
                $query->with(['fornecedor', 'formacaoPreco']);
            }
        ]);

        // Calcular validade proporcional
        $validadeProposta = $this->calcularValidadeProposta($processo);

        // Obter dados completos da empresa do tenant atual
        $tenant = null;
        $nomeEmpresa = 'Empresa não identificada';
        $cnpjEmpresa = '';
        $enderecoEmpresa = '';
        $cidadeEmpresa = '';
        $estadoEmpresa = '';
        $emailEmpresa = '';
        $telefoneEmpresa = '';
        $nomeFantasia = '';
        $bancoEmpresa = '';
        $agenciaEmpresa = '';
        $contaEmpresa = '';
        $representanteLegal = '';
        
        try {
            if (tenancy()->initialized) {
                $tenant = tenant();
                if ($tenant) {
                    $nomeEmpresa = $tenant->razao_social ?? 'Empresa não identificada';
                    $cnpjEmpresa = $tenant->cnpj ?? '';
                    $enderecoEmpresa = $tenant->endereco ?? '';
                    $cidadeEmpresa = $tenant->cidade ?? '';
                    $estadoEmpresa = $tenant->estado ?? '';
                    $emailEmpresa = $tenant->email ?? '';
                    $telefones = $tenant->telefones ?? [];
                    $telefoneEmpresa = is_array($telefones) && !empty($telefones) ? $telefones[0] : '';
                    // Nome fantasia não existe no Tenant, usar razão social
                    $nomeFantasia = $nomeEmpresa;
                    $bancoEmpresa = $tenant->banco ?? '';
                    $agenciaEmpresa = $tenant->agencia ?? '';
                    $contaEmpresa = $tenant->conta ?? '';
                    $representanteLegal = $tenant->representante_legal_nome ?? '';
                }
            }
        } catch (\Exception $e) {
            // Se houver erro, manter valores padrão
        }

        // Carregar logo da empresa se existir
        $logoUrl = null;
        $logoBase64 = null;
        try {
            if ($tenant && $tenant->logo) {
                $logoValue = $tenant->logo;
                
                // Verificar se já é base64
                if (str_starts_with($logoValue, 'data:image')) {
                    $logoBase64 = $logoValue;
                }
                // Verificar se é uma URL
                elseif (filter_var($logoValue, FILTER_VALIDATE_URL)) {
                    $logoUrl = $logoValue;
                }
                // Tentar como caminho de arquivo no storage public
                elseif (Storage::disk('public')->exists($logoValue)) {
                    $logoPath = Storage::disk('public')->path($logoValue);
                    if (file_exists($logoPath) && is_readable($logoPath)) {
                        $logoContent = file_get_contents($logoPath);
                        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                        $mimeType = 'png'; // padrão
                        if (in_array($extension, ['jpg', 'jpeg'])) {
                            $mimeType = 'jpeg';
                        } elseif ($extension === 'png') {
                            $mimeType = 'png';
                        } elseif ($extension === 'gif') {
                            $mimeType = 'gif';
                        } elseif ($extension === 'webp') {
                            $mimeType = 'webp';
                        } elseif ($extension === 'svg') {
                            $mimeType = 'svg+xml';
                        }
                        $logoBase64 = 'data:image/' . $mimeType . ';base64,' . base64_encode($logoContent);
                    }
                }
                // Tentar como caminho absoluto
                elseif (file_exists($logoValue) && is_readable($logoValue)) {
                    $logoContent = file_get_contents($logoValue);
                    $extension = strtolower(pathinfo($logoValue, PATHINFO_EXTENSION));
                    $mimeType = 'png'; // padrão
                    if (in_array($extension, ['jpg', 'jpeg'])) {
                        $mimeType = 'jpeg';
                    } elseif ($extension === 'png') {
                        $mimeType = 'png';
                    } elseif ($extension === 'gif') {
                        $mimeType = 'gif';
                    } elseif ($extension === 'webp') {
                        $mimeType = 'webp';
                    } elseif ($extension === 'svg') {
                        $mimeType = 'svg+xml';
                    }
                    $logoBase64 = 'data:image/' . $mimeType . ';base64,' . base64_encode($logoContent);
                }
                // Tentar caminho relativo a partir do storage/public
                else {
                    $possiblePaths = [
                        storage_path('app/public/' . $logoValue),
                        storage_path('app/public/logos/' . $logoValue),
                        public_path('storage/' . $logoValue),
                        public_path('storage/logos/' . $logoValue),
                    ];
                    
                    foreach ($possiblePaths as $possiblePath) {
                        if (file_exists($possiblePath) && is_readable($possiblePath)) {
                            $logoContent = file_get_contents($possiblePath);
                            $extension = strtolower(pathinfo($possiblePath, PATHINFO_EXTENSION));
                            $mimeType = 'png'; // padrão
                            if (in_array($extension, ['jpg', 'jpeg'])) {
                                $mimeType = 'jpeg';
                            } elseif ($extension === 'png') {
                                $mimeType = 'png';
                            } elseif ($extension === 'gif') {
                                $mimeType = 'gif';
                            } elseif ($extension === 'webp') {
                                $mimeType = 'webp';
                            } elseif ($extension === 'svg') {
                                $mimeType = 'svg+xml';
                            }
                            $logoBase64 = 'data:image/' . $mimeType . ';base64,' . base64_encode($logoContent);
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Se houver erro ao carregar logo, continuar sem logo
            \Log::warning('Erro ao carregar logo do tenant', [
                'tenant_id' => $tenant?->id,
                'logo' => $tenant->logo ?? null,
                'error' => $e->getMessage()
            ]);
        }

        // Formatar endereço completo
        $enderecoCompleto = trim(implode(', ', array_filter([
            $enderecoEmpresa,
            $cidadeEmpresa,
            $estadoEmpresa
        ])));

        // Formatar data atual
        $dataAtual = Carbon::now();
        $dataFormatada = $dataAtual->format('d \d\e F \d\e Y');
        $meses = [
            'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
            'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
            'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
            'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
        ];
        foreach ($meses as $en => $pt) {
            $dataFormatada = str_replace($en, $pt, $dataFormatada);
        }

        // Filtrar apenas itens que têm valor_arrematado preenchido
        $itensComValorArrematado = $processo->itens->filter(function ($item) {
            return !empty($item->valor_arrematado) && $item->valor_arrematado > 0;
        })->values();

        $dados = [
            'processo' => $processo,
            'validade_proposta' => $validadeProposta,
            'data_elaboracao' => Carbon::now()->format('d/m/Y H:i'),
            'data_formatada' => $dataFormatada,
            'itens' => $itensComValorArrematado,
            'nome_empresa' => $nomeEmpresa,
            'nome_fantasia' => $nomeFantasia,
            'cnpj_empresa' => $cnpjEmpresa,
            'endereco_completo' => $enderecoCompleto,
            'cidade_empresa' => $cidadeEmpresa,
            'estado_empresa' => $estadoEmpresa,
            'email_empresa' => $emailEmpresa,
            'telefone_empresa' => $telefoneEmpresa,
            'banco_empresa' => $bancoEmpresa,
            'agencia_empresa' => $agenciaEmpresa,
            'conta_empresa' => $contaEmpresa,
            'representante_legal' => $representanteLegal,
            'tenant' => $tenant,
            'logo_url' => $logoUrl,
            'logo_base64' => $logoBase64,
        ];

        // Retornar HTML para conversão em PDF
        return View::make('exports.proposta_comercial', $dados)->render();
    }

    /**
     * Gera catálogo/ficha técnica em PDF
     */
    public function gerarCatalogoFichaTecnica(Processo $processo): string
    {
        $processo->load([
            'orgao',
            'setor',
            'itens' => function ($query) {
                $query->orderBy('numero_item');
            },
            'itens.orcamentos' => function ($query) {
                $query->with(['fornecedor', 'formacaoPreco']);
            }
        ]);

        // Obter nome da empresa do tenant atual
        $nomeEmpresa = 'Empresa não identificada';
        try {
            if (tenancy()->initialized) {
                $tenant = tenant();
                $nomeEmpresa = $tenant ? ($tenant->razao_social ?? 'Empresa não identificada') : 'Empresa não identificada';
            }
        } catch (\Exception $e) {
            // Se houver erro, manter valor padrão
        }

        $dados = [
            'processo' => $processo,
            'data_elaboracao' => Carbon::now()->format('d/m/Y H:i'),
            'itens' => $processo->itens,
            'nome_empresa' => $nomeEmpresa,
        ];

        return View::make('exports.catalogo_ficha_tecnica', $dados)->render();
    }

    /**
     * Calcula validade da proposta
     * Agora a validade é armazenada como string (ex: "60 dias úteis")
     */
    protected function calcularValidadeProposta(Processo $processo): string
    {
        // Se já existe validade_proposta como string, retornar diretamente
        if ($processo->validade_proposta) {
            return $processo->validade_proposta;
        }

        // Fallback para processos antigos que ainda usam validade_proposta_inicio/fim
        if ($processo->validade_proposta_inicio && $processo->validade_proposta_fim) {
            $inicio = Carbon::parse($processo->validade_proposta_inicio);
            $fim = Carbon::parse($processo->validade_proposta_fim);
            $hoje = Carbon::now();

            // Se hoje está dentro do período
            if ($hoje->between($inicio, $fim)) {
                $diasRestantes = $hoje->diffInDays($fim);
                return "Válida até {$fim->format('d/m/Y')} ({$diasRestantes} dias restantes)";
            }

            // Se já passou
            if ($hoje->isAfter($fim)) {
                return "Vencida em {$fim->format('d/m/Y')}";
            }

            // Se ainda não começou
            return "Válida de {$inicio->format('d/m/Y')} até {$fim->format('d/m/Y')}";
        }

        return 'Não especificada';
    }
}

