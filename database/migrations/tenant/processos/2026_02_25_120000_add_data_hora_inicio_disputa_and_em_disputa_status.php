<?php

use App\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public string $table = 'processos';

    /**
     * Run the migrations.
     * Adiciona data_hora_inicio_disputa (intervalo de disputa) e garante status 'em_disputa' no enum.
     */
    public function up(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->dateTime('data_hora_inicio_disputa')->nullable()->after('data_hora_sessao_publica')
                ->comment('Início do intervalo em que o processo fica em disputa; fim = data_hora_sessao_publica');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            $this->addEmDisputaToStatusEnumPg();
        } else {
            $this->addEmDisputaToStatusEnumMysql();
        }
    }

    private function addEmDisputaToStatusEnumPg(): void
    {
        $constraint = 'processos_status_check';
        $newValues = [
            'participacao',
            'em_disputa',
            'julgamento_habilitacao',
            'vencido',
            'perdido',
            'execucao',
            'pagamento',
            'encerramento',
            'arquivado',
        ];
        $enumList = array_map(fn ($v) => "'{$v}'::character varying", $newValues);
        $enumString = implode(', ', $enumList);

        DB::transaction(function () use ($constraint, $enumString) {
            try {
                DB::statement("ALTER TABLE processos DROP CONSTRAINT IF EXISTS {$constraint}");
            } catch (\Throwable $e) {
                // Pode não existir com esse nome exato
            }
            DB::statement("ALTER TABLE processos ADD CONSTRAINT {$constraint} CHECK (status::text = ANY (ARRAY[{$enumString}]::text[]))");
        });
    }

    private function addEmDisputaToStatusEnumMysql(): void
    {
        DB::statement("ALTER TABLE processos MODIFY COLUMN status ENUM(
            'participacao',
            'em_disputa',
            'julgamento_habilitacao',
            'vencido',
            'perdido',
            'execucao',
            'pagamento',
            'encerramento',
            'arquivado'
        ) NOT NULL DEFAULT 'participacao'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('processos')->where('status', 'em_disputa')->update(['status' => 'participacao']);

        Schema::table('processos', function (Blueprint $table) {
            $table->dropColumn('data_hora_inicio_disputa');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            $constraint = 'processos_status_check';
            $values = [
                'participacao',
                'julgamento_habilitacao',
                'vencido',
                'perdido',
                'execucao',
                'pagamento',
                'encerramento',
                'arquivado',
            ];
            $enumList = array_map(fn ($v) => "'{$v}'::character varying", $values);
            $enumString = implode(', ', $enumList);
            DB::transaction(function () use ($constraint, $enumString) {
                DB::statement("ALTER TABLE processos DROP CONSTRAINT IF EXISTS {$constraint}");
                DB::statement("ALTER TABLE processos ADD CONSTRAINT {$constraint} CHECK (status::text = ANY (ARRAY[{$enumString}]::text[]))");
            });
        }
        // MySQL: reverter enum removendo em_disputa exigiria outro MODIFY; deixar em_disputa no down é aceitável
    }
};
