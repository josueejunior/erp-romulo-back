<?php

namespace App\Modules\Processo\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;

class ExportacaoService
{
    /**
     * Validar processo pertence à empresa
     */
    public function validarProcessoEmpresa(Processo $processo, int $empresaId): void
    {
        if ($processo->empresa_id !== $empresaId) {
            throw new \Exception('Processo não encontrado ou não pertence à empresa ativa.');
        }
    }

    /**
     * Validar processo pode ser exportado
     */
    public function validarProcessoPodeExportar(Processo $processo): void
    {
        if (in_array($processo->status, ['arquivado', 'perdido'])) {
            throw new \Exception('Não é possível exportar para processos arquivados ou perdidos.');
        }
    }

    /**
     * Gerar PDF a partir de HTML
     */
    public function gerarPDF(string $html, string $filename): ?string
    {
        try {
            // Tentar usar dompdf se disponível
            if (class_exists(\Dompdf\Dompdf::class)) {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                
                return $dompdf->output();
            }
        } catch (\Exception $e) {
            // Em caso de erro, retornar null para usar HTML
        }

        return null;
    }

    /**
     * Gera proposta comercial em HTML
     */
    public function gerarPropostaComercial(Processo $processo): string
    {
        $processo->load([
            'orgao',
            'setor',
            'itens' => function ($query) {
                $query->orderBy('numero_item');
            },
            'itens.orcamentos' => function ($query) use ($processo) {
                // Remover scope global que causa ambiguidade
                $query->withoutGlobalScope('empresa');
                // Especificar a tabela explicitamente para evitar ambiguidade de empresa_id
                $query->where('orcamentos.empresa_id', $processo->empresa_id)
                      ->whereNotNull('orcamentos.empresa_id')
                      ->with(['fornecedor', 'formacaoPreco']);
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
        $cargoRepresentante = '';
        
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
                    
                    // 🔥 CORREÇÃO: Garantir que telefone seja string
                    $telefones = $tenant->telefones ?? [];
                    if (is_array($telefones) && !empty($telefones)) {
                        $telefoneEmpresa = is_array($telefones[0]) || is_object($telefones[0])
                            ? (string)($telefones[0]->numero ?? $telefones[0]['numero'] ?? $telefones[0] ?? '')
                            : (string)$telefones[0];
                    } else {
                        $telefoneEmpresa = '';
                    }
                    
                    // Usar nome_fantasia se existir, senão usar razão social
                    $nomeFantasia = $tenant->nome_fantasia ?? $nomeEmpresa;
                    $bancoEmpresa = $tenant->banco ?? '';
                    $agenciaEmpresa = $tenant->agencia ?? '';
                    $contaEmpresa = $tenant->conta ?? '';
                    $representanteLegal = $tenant->representante_legal_nome ?? '';
                    $cargoRepresentante = $tenant->cargo_representante ?? '';
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

        // 🔥 CORREÇÃO: Formatar endereço completo garantindo que todos os valores sejam strings
        $enderecoCompleto = trim(implode(', ', array_filter([
            is_array($enderecoEmpresa) ? implode(', ', $enderecoEmpresa) : (string)($enderecoEmpresa ?? ''),
            is_array($cidadeEmpresa) ? implode(', ', $cidadeEmpresa) : (string)($cidadeEmpresa ?? ''),
            is_array($estadoEmpresa) ? implode(', ', $estadoEmpresa) : (string)($estadoEmpresa ?? '')
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

        // 🔥 CORREÇÃO: Garantir que todas as variáveis sejam strings para evitar erro no htmlspecialchars
        $dados = [
            'processo' => $processo,
            'validade_proposta' => $validadeProposta,
            'data_elaboracao' => Carbon::now()->format('d/m/Y H:i'),
            'data_formatada' => $dataFormatada,
            'itens' => $itensComValorArrematado,
            'nome_empresa' => is_array($nomeEmpresa) ? implode(', ', $nomeEmpresa) : (string)($nomeEmpresa ?? ''),
            'nome_fantasia' => is_array($nomeFantasia) ? implode(', ', $nomeFantasia) : (string)($nomeFantasia ?? ''),
            'cnpj_empresa' => is_array($cnpjEmpresa) ? implode(', ', $cnpjEmpresa) : (string)($cnpjEmpresa ?? ''),
            'endereco_completo' => $enderecoCompleto ?: '',
            'cidade_empresa' => is_array($cidadeEmpresa) ? implode(', ', $cidadeEmpresa) : (string)($cidadeEmpresa ?? ''),
            'estado_empresa' => is_array($estadoEmpresa) ? implode(', ', $estadoEmpresa) : (string)($estadoEmpresa ?? ''),
            'email_empresa' => is_array($emailEmpresa) ? implode(', ', $emailEmpresa) : (string)($emailEmpresa ?? ''),
            'telefone_empresa' => $telefoneEmpresa ?: '',
            'banco_empresa' => is_array($bancoEmpresa) ? implode(', ', $bancoEmpresa) : (string)($bancoEmpresa ?? ''),
            'agencia_empresa' => is_array($agenciaEmpresa) ? implode(', ', $agenciaEmpresa) : (string)($agenciaEmpresa ?? ''),
            'conta_empresa' => is_array($contaEmpresa) ? implode(', ', $contaEmpresa) : (string)($contaEmpresa ?? ''),
            'representante_legal' => is_array($representanteLegal) ? implode(', ', $representanteLegal) : (string)($representanteLegal ?? ''),
            'cargo_representante' => is_array($cargoRepresentante) ? implode(', ', $cargoRepresentante) : (string)($cargoRepresentante ?? ''),
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
            'itens.orcamentos' => function ($query) use ($processo) {
                // Remover scope global que causa ambiguidade
                $query->withoutGlobalScope('empresa');
                // Especificar a tabela explicitamente para evitar ambiguidade de empresa_id
                $query->where('orcamentos.empresa_id', $processo->empresa_id)
                      ->whereNotNull('orcamentos.empresa_id')
                      ->with(['fornecedor', 'formacaoPreco']);
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

