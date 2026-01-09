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

        $mailDriver = config('mail.default');
        $mailHost = config('mail.mailers.smtp.host');
        $mailPort = config('mail.mailers.smtp.port');
        $mailUsername = config('mail.mailers.smtp.username');
        $mailPassword = config('mail.mailers.smtp.password') ? '***' : 'NÃO DEFINIDO';
        $mailEncryption = config('mail.mailers.smtp.encryption');
        $mailFrom = config('mail.from.address');
        $mailFromName = config('mail.from.name');

        $this->table(
            ['Configuração', 'Valor'],
            [
                ['Driver', $mailDriver],
                ['Host', $mailHost],
                ['Porta', $mailPort],
                ['Criptografia', $mailEncryption],
                ['Usuário', $mailUsername],
                ['Senha', $mailPassword],
                ['Email Remetente', $mailFrom],
                ['Nome Remetente', $mailFromName],
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

