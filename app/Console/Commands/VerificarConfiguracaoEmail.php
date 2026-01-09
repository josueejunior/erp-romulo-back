<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class VerificarConfiguracaoEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:verificar-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica a configuração de email do sistema';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando configuração de email...');
        $this->newLine();
        
        // 🔥 IMPORTANTE: Verificar se configuração está em cache
        if (app()->configurationIsCached()) {
            $this->warn('⚠️  ATENÇÃO: Configuração está em cache!');
            $this->line('   Execute: php artisan config:clear');
            $this->newLine();
        }

        // Ler diretamente do .env (bypass cache) para diagnóstico
        $mailDriver = env('MAIL_MAILER', config('mail.default'));
        $mailHost = env('MAIL_HOST', config('mail.mailers.smtp.host'));
        $mailPort = env('MAIL_PORT', config('mail.mailers.smtp.port'));
        $mailUsername = env('MAIL_USERNAME', config('mail.mailers.smtp.username'));
        $mailPassword = env('MAIL_PASSWORD', config('mail.mailers.smtp.password'));
        $mailEncryption = env('MAIL_ENCRYPTION', config('mail.mailers.smtp.encryption'));
        $mailFrom = env('MAIL_FROM_ADDRESS', config('mail.from.address'));
        $mailFromName = env('MAIL_FROM_NAME', config('mail.from.name'));
        
        // Remover aspas da senha se houver (problema comum no .env)
        if ($mailPassword && (str_starts_with($mailPassword, '"') || str_starts_with($mailPassword, "'"))) {
            $this->warn('⚠️  ATENÇÃO: Senha tem aspas no .env! Remova as aspas de MAIL_PASSWORD');
            $this->line('   Exemplo: MAIL_PASSWORD=C/k6@!S0  (sem aspas)');
            $this->newLine();
        }

        $this->table(
            ['Configuração', 'Valor (do .env)', 'Valor (do cache)'],
            [
                ['Driver', env('MAIL_MAILER', 'não definido'), config('mail.default')],
                ['Host', env('MAIL_HOST', 'não definido'), config('mail.mailers.smtp.host')],
                ['Porta', env('MAIL_PORT', 'não definido'), config('mail.mailers.smtp.port')],
                ['Criptografia', env('MAIL_ENCRYPTION', 'não definido'), config('mail.mailers.smtp.encryption')],
                ['Usuário', env('MAIL_USERNAME', 'não definido'), config('mail.mailers.smtp.username')],
                ['Senha', $mailPassword ? '***definido***' : 'NÃO DEFINIDO', config('mail.mailers.smtp.password') ? '***definido***' : 'NÃO DEFINIDO'],
                ['Email Remetente', env('MAIL_FROM_ADDRESS', 'não definido'), config('mail.from.address')],
                ['Nome Remetente', env('MAIL_FROM_NAME', 'não definido'), config('mail.from.name')],
            ]
        );

        $this->newLine();

        // Validações
        $erros = [];
        $avisos = [];

        if ($mailDriver === 'smtp') {
            if (empty($mailHost)) {
                $erros[] = 'MAIL_HOST não está definido no .env';
            } elseif (in_array(strtolower($mailHost), ['mailpit', 'localhost', '127.0.0.1'])) {
                $erros[] = "MAIL_HOST está configurado para '{$mailHost}' (configuração de desenvolvimento). Use um servidor SMTP de produção.";
            }

            if (empty($mailPort)) {
                $erros[] = 'MAIL_PORT não está definido no .env';
            }

            if (empty($mailUsername)) {
                $erros[] = 'MAIL_USERNAME não está definido no .env';
            }

            if (empty(config('mail.mailers.smtp.password'))) {
                $erros[] = 'MAIL_PASSWORD não está definido no .env';
            }

            if (empty($mailEncryption)) {
                $avisos[] = 'MAIL_ENCRYPTION não está definido. Recomendado: ssl ou tls';
            }
        }

        if (!empty($erros)) {
            $this->error('❌ Erros encontrados na configuração:');
            foreach ($erros as $erro) {
                $this->line("  - {$erro}");
            }
            $this->newLine();
            $this->info('📝 Para corrigir, edite o arquivo .env e configure:');
            $this->line('MAIL_MAILER=smtp');
            $this->line('MAIL_HOST=smtp.hostinger.com');
            $this->line('MAIL_PORT=465');
            $this->line('MAIL_ENCRYPTION=ssl');
            $this->line('MAIL_USERNAME=naoresponda@addsimp.com');
            $this->line('MAIL_PASSWORD=sua_senha_aqui');
            $this->line('MAIL_FROM_ADDRESS=naoresponda@addsimp.com');
            $this->line('MAIL_FROM_NAME="Sistema ERP - Gestão de Licitações"');
            $this->newLine();
            $this->info('💡 Após editar o .env, execute: php artisan config:clear');
            return 1;
        }

        if (!empty($avisos)) {
            $this->warn('⚠️  Avisos:');
            foreach ($avisos as $aviso) {
                $this->line("  - {$aviso}");
            }
            $this->newLine();
        }

        $this->info('✅ Configuração de email parece estar correta!');
        $this->newLine();
        $this->info('🧪 Para testar o envio de email, execute:');
        $this->line('php artisan email:testar');

        return 0;
    }
}

