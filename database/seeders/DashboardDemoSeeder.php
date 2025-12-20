<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Processo;
use App\Models\DocumentoHabilitacao;
use App\Models\Orgao;
use App\Models\Setor;
use Carbon\Carbon;

class DashboardDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cria um órgão e setor padrão para vincular aos processos
        $orgao = Orgao::firstOrCreate(
            ['razao_social' => 'Órgão de Teste - Dashboard'],
            [
                'uasg' => '999999',
                'cnpj' => null,
                'endereco' => 'Rua de Teste, 123',
                'cidade' => 'Cidade Teste',
                'estado' => 'SP',
                'cep' => '00000-000',
                'email' => 'contato@orgao-teste.local',
                'telefone' => '(11) 0000-0000',
                'observacoes' => 'Criado automaticamente pelo DashboardDemoSeeder.',
            ]
        );

        $setor = Setor::firstOrCreate(
            ['orgao_id' => $orgao->id, 'nome' => 'Setor de Compras'],
            [
                'email' => 'compras@orgao-teste.local',
                'telefone' => '(11) 0000-0001',
                'observacoes' => 'Setor padrão para testes de dashboard.',
            ]
        );

        // Processos em diferentes etapas
        $now = Carbon::now();

        $processos = [
            [
                'status' => 'participacao',
                'orgao_id' => $orgao->id,
                'setor_id' => $setor->id,
                'modalidade' => 'pregao',
                'numero_modalidade' => 'PRG-001/2025',
                'objeto_resumido' => 'Aquisição de materiais de escritório',
                'data_hora_sessao_publica' => $now->copy()->addDays(3),
            ],
            [
                'status' => 'participacao',
                'orgao_id' => $orgao->id,
                'setor_id' => $setor->id,
                'modalidade' => 'pregao',
                'numero_modalidade' => 'PRG-002/2025',
                'objeto_resumido' => 'Serviços de manutenção predial',
                'data_hora_sessao_publica' => $now->copy()->addDays(5),
            ],
            [
                'status' => 'julgamento_habilitacao',
                'orgao_id' => $orgao->id,
                'setor_id' => $setor->id,
                'modalidade' => 'dispensa',
                'numero_modalidade' => 'DL-010/2025',
                'objeto_resumido' => 'Locação de veículos',
                'data_hora_sessao_publica' => $now->copy()->addDays(2),
            ],
            [
                'status' => 'execucao',
                'orgao_id' => $orgao->id,
                'setor_id' => $setor->id,
                'modalidade' => 'pregao',
                'numero_modalidade' => 'PRG-050/2024',
                'objeto_resumido' => 'Fornecimento de equipamentos de TI',
                'data_hora_sessao_publica' => $now->copy()->subDays(30),
            ],
            [
                'status' => 'execucao',
                'orgao_id' => $orgao->id,
                'setor_id' => $setor->id,
                'modalidade' => 'dispensa',
                'numero_modalidade' => 'DL-080/2024',
                'objeto_resumido' => 'Serviços de limpeza',
                'data_hora_sessao_publica' => $now->copy()->subDays(10),
            ],
        ];

        foreach ($processos as $p) {
            Processo::create($p);
        }

        // Documentos vencendo e vencidos
        $documentos = [
            [
                'tipo' => 'Certidão Negativa',
                'numero' => '123456',
                'data_validade' => $now->copy()->addDays(5),
            ],
            [
                'tipo' => 'Alvará',
                'numero' => 'ALV-789',
                'data_validade' => $now->copy()->addDays(15),
            ],
            [
                'tipo' => 'Balanço',
                'numero' => 'BAL-2024',
                'data_validade' => $now->copy()->subDays(2),
            ],
        ];

        foreach ($documentos as $d) {
            DocumentoHabilitacao::create($d);
        }
    }
}


