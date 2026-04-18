<?php

namespace App\Application\Processo\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Modules\Processo\Resources\ProcessoListResource;
use Illuminate\Support\Facades\Log;

/**
 * Use Case para exportar processos
 * 
 * Responsabilidades:
 * - Buscar processos com filtros
 * - Formatar para CSV ou JSON
 * - Retornar resposta HTTP apropriada
 */
class ExportarProcessosUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executa o use case
     * 
     * @param array $filtros Filtros de busca
     * @param string $formato Formato de exportação (csv ou json)
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function executar(array $filtros, string $formato = 'csv')
    {
        // Preparar filtros para buscar todos (sem paginação)
        $filtros['per_page'] = 10000; // Limite alto para exportar todos

        // Buscar processos via repository
        $paginado = $this->processoRepository->buscarComFiltros($filtros);

        // Buscar modelos Eloquent com relacionamentos necessários
        $processosIds = $paginado->getCollection()->pluck('id')->toArray();
        
        if (empty($processosIds)) {
            if ($formato === 'json') {
                return response()->json([
                    'data' => [],
                    'meta' => ['total' => 0],
                ]);
            }
            // CSV vazio
            return $this->exportarCSVVazio();
        }

        // Buscar modelos com relacionamentos
        $processos = \App\Modules\Processo\Models\Processo::whereIn('id', $processosIds)
            ->with(['orgao', 'setor', 'itens'])
            ->get();

        if ($formato === 'json') {
            return response()->json([
                'data' => ProcessoListResource::collection($processos),
                'meta' => [
                    'total' => $paginado->total(),
                ],
            ]);
        }

        // Exportar CSV
        return $this->exportarCSV($processos);
    }

    /**
     * Exporta processos para CSV
     */
    private function exportarCSV($processos)
    {
        $filename = 'processos_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($processos) {
            $file = fopen('php://output', 'w');
            
            // Adicionar BOM UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalhos
            fputcsv($file, [
                'ID',
                'Identificador',
                'Número Modalidade',
                'Modalidade',
                'Número Processo Administrativo',
                'Órgão',
                'UASG',
                'Setor',
                'Objeto Resumido',
                'Status',
                'Status Label',
                'Fase Atual',
                'Data Sessão Pública',
                'Próxima Data',
                'Valor Estimado',
                'Valor Mínimo',
                'Valor Vencido',
                'Resultado',
                'Tem Alerta',
                'Data Criação',
                'Data Atualização',
            ], ';');

            // Dados
            foreach ($processos as $processo) {
                $resource = new ProcessoListResource($processo);
                $data = $resource->toArray(request());
                
                $proximaData = $data['proxima_data'] 
                    ? ($data['proxima_data']['data'] ?? '') . ' - ' . ($data['proxima_data']['tipo'] ?? '')
                    : '';
                
                $alertas = $data['alertas'] ?? [];
                $temAlerta = !empty($alertas);

                fputcsv($file, [
                    $data['id'] ?? '',
                    $data['identificador'] ?? '',
                    $data['numero_modalidade'] ?? '',
                    $data['modalidade'] ?? '',
                    $data['numero_processo_administrativo'] ?? '',
                    $data['orgao']['razao_social'] ?? '',
                    $data['orgao']['uasg'] ?? '',
                    $data['setor']['nome'] ?? '',
                    $data['objeto_resumido'] ?? '',
                    $data['status'] ?? '',
                    $data['status_label'] ?? '',
                    $data['fase_atual'] ?? '',
                    $data['data_sessao_publica_formatted'] ?? '',
                    $proximaData,
                    number_format($data['valores']['estimado'] ?? 0, 2, ',', '.'),
                    $data['valores']['minimo'] ? number_format($data['valores']['minimo'], 2, ',', '.') : '',
                    $data['valores']['vencido'] ? number_format($data['valores']['vencido'], 2, ',', '.') : '',
                    $data['resultado'] ?? '',
                    $temAlerta ? 'Sim' : 'Não',
                    $data['created_at'] ?? '',
                    $data['updated_at'] ?? '',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Retorna CSV vazio
     */
    private function exportarCSVVazio()
    {
        $filename = 'processos_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['Nenhum processo encontrado'], ';');
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

