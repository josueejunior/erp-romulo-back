<?php

declare(strict_types=1);

namespace App\Application\SupportTicket\UseCases;

use App\Domain\Exceptions\NotFoundException;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Mail\TicketRespondidoEmail;
use App\Models\SupportTicket;
use App\Models\SupportTicketResponse;
use App\Modules\Auth\Models\User;
use App\Services\AdminTenancyRunner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Use Case: Adicionar resposta do admin a um ticket de suporte.
 * Envia e-mail para o usuário/empresa quando o admin responde.
 */
class AdicionarRespostaTicketAdminUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * @param int $ticketId ID do ticket
     * @param int $tenantId ID da empresa (obrigatório)
     * @param string $mensagem Conteúdo da resposta
     * @return \App\Models\SupportTicketResponse Resposta criada (com supportTicket carregado)
     */
    public function executar(int $ticketId, int $tenantId, string $mensagem): SupportTicketResponse
    {
        $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
        if (! $tenantDomain) {
            throw new NotFoundException('Tenant');
        }

        $useTenantDatabases = config('tenancy.database.use_tenant_databases', false);
        $empresaNome = $tenantDomain->razaoSocial ?? $tenantDomain->nomeFantasia ?? ('Empresa #' . $tenantId);

        $doStore = function () use ($ticketId, $mensagem, $empresaNome) {
            $ticket = SupportTicket::query()->find($ticketId);
            if ($ticket === null) {
                return null;
            }
            $response = SupportTicketResponse::create([
                'support_ticket_id' => $ticket->id,
                'author_type' => 'admin',
                'author_id' => null,
                'mensagem' => $mensagem,
            ]);
            if ($ticket->status === 'aberto') {
                $ticket->update(['status' => 'em_atendimento']);
            }

            $this->enviarEmailResposta($ticket, $mensagem, $empresaNome);

            return $response->load('supportTicket');
        };

        if (! $useTenantDatabases) {
            $ticket = SupportTicket::query()->where('tenant_id', $tenantId)->find($ticketId);
            if ($ticket === null) {
                throw new NotFoundException('Ticket');
            }
            $result = $doStore();
            assert($result instanceof SupportTicketResponse);
            return $result;
        }

        $result = $this->adminTenancyRunner->runForTenant($tenantDomain, $doStore);
        if ($result === null) {
            throw new NotFoundException('Ticket');
        }
        return $result;
    }

    private function enviarEmailResposta(SupportTicket $ticket, string $mensagem, string $empresaNome): void
    {
        // Log de entrada (nível warning para aparecer mesmo com LOG_LEVEL=warning)
        Log::channel('single')->warning('AdicionarRespostaTicketAdminUseCase: tentando enviar e-mail de resposta ao ticket', [
            'ticket_id' => $ticket->id,
            'ticket_numero' => $ticket->numero,
            'user_id' => $ticket->user_id,
            'connection' => $ticket->getConnection()->getName(),
        ]);

        // Mesma conexão do ticket (central ou tenant) e sem global scope para achar o usuário no contexto admin
        $connection = $ticket->getConnection()->getName();
        $user = User::on($connection)->withoutGlobalScopes()->find($ticket->user_id);

        if ($user === null || empty(trim((string) $user->email))) {
            Log::channel('single')->warning('AdicionarRespostaTicketAdminUseCase: e-mail NÃO enviado — usuário não encontrado ou sem e-mail', [
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->user_id,
                'user_found' => $user !== null,
            ]);
            return;
        }

        $email = trim($user->email);
        $nomeUsuario = $user->name ?? $user->email ?? 'Usuário';

        try {
            Mail::to($email)->send(new TicketRespondidoEmail(
                $ticket->numero,
                $empresaNome,
                $nomeUsuario,
                $mensagem,
            ));
            Log::channel('single')->info('AdicionarRespostaTicketAdminUseCase: e-mail de resposta ENVIADO com sucesso', [
                'ticket_id' => $ticket->id,
                'user_email' => $email,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('AdicionarRespostaTicketAdminUseCase: ERRO ao enviar e-mail de resposta do ticket', [
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'user_email' => $email,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
