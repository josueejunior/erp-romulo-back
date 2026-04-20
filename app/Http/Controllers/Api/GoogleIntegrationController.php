<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserGoogleIntegration;
use App\Modules\Processo\Models\Processo;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleIntegrationController extends Controller
{
    public function __construct(
        private GoogleCalendarService $googleCalendarService
    ) {}

    public function authorize(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        $state = Str::uuid()->toString();
        Cache::put($this->stateCacheKey($state), $user->id, now()->addMinutes(10));

        $authData = $this->googleCalendarService->buildAuthorizationUrl($state);

        return response()->json([
            'success' => true,
            'data' => $authData,
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $frontendUrl = (string) config('google.frontend_redirect_url');
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        $error = (string) $request->query('error', '');

        if ($error !== '') {
            return redirect()->to($frontendUrl.'?google=error&mensagem='.urlencode($error));
        }

        if ($state === '' || $code === '') {
            return redirect()->to($frontendUrl.'?google=error&mensagem='.urlencode('Parâmetros OAuth inválidos.'));
        }

        $cacheKey = $this->stateCacheKey($state);
        $userId = Cache::get($cacheKey);
        Cache::forget($cacheKey);

        if (!$userId) {
            return redirect()->to($frontendUrl.'?google=error&mensagem='.urlencode('State OAuth expirado.'));
        }

        $user = \App\Modules\Auth\Models\User::query()->find($userId);
        if (!$user) {
            return redirect()->to($frontendUrl.'?google=error&mensagem='.urlencode('Usuário não encontrado.'));
        }

        try {
            $this->googleCalendarService->handleOAuthCallback($user, $code);
        } catch (\Throwable $e) {
            Log::error('GoogleIntegrationController::callback - erro OAuth', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->to($frontendUrl.'?google=error&mensagem='.urlencode('Falha ao conectar Google Calendar.'));
        }

        return redirect()->to($frontendUrl.'?google=connected');
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        $integration = UserGoogleIntegration::query()->where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'connected' => (bool) $integration,
                'google_email' => $integration?->google_email,
                'connected_at' => $integration?->connected_at,
                'token_expires_at' => $integration?->token_expires_at,
            ],
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        UserGoogleIntegration::query()->where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conta Google desconectada com sucesso.',
        ]);
    }

    public function enviarProcessoParaCalendario(Request $request, Processo $processo): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado.'], 401);
        }

        if ((int) $user->empresa_ativa_id !== (int) $processo->empresa_id) {
            return response()->json([
                'success' => false,
                'message' => 'Processo não pertence à empresa ativa.',
            ], 403);
        }

        $inicio = $processo->data_hora_sessao_publica;
        $fim = $inicio ? Carbon::parse($inicio)->addHour() : null;

        if (!$inicio && $processo->validade_proposta_fim) {
            $inicio = Carbon::parse($processo->validade_proposta_fim)->setTime(9, 0, 0);
            $fim = Carbon::parse($inicio)->addHour();
        }

        if (!$inicio || !$fim) {
            return response()->json([
                'success' => false,
                'message' => 'Este processo não possui data para criar evento no calendário.',
            ], 422);
        }

        $eventPayload = [
            'summary' => sprintf(
                'Licitação %s',
                $processo->numero_modalidade ?: '#'.$processo->id
            ),
            'description' => trim(implode("\n", array_filter([
                $processo->objeto_resumido ? 'Objeto: '.$processo->objeto_resumido : null,
                $processo->numero_processo_administrativo ? 'Processo ADM: '.$processo->numero_processo_administrativo : null,
                $processo->modalidade ? 'Modalidade: '.$processo->modalidade : null,
            ]))),
            'location' => $processo->local_entrega_detalhado ?: $processo->endereco_entrega,
            'start' => [
                'dateTime' => Carbon::parse($inicio)->toRfc3339String(),
                'timeZone' => 'America/Sao_Paulo',
            ],
            'end' => [
                'dateTime' => Carbon::parse($fim)->toRfc3339String(),
                'timeZone' => 'America/Sao_Paulo',
            ],
        ];

        try {
            $createdEvent = $this->googleCalendarService->createEventForUser($user, $eventPayload);
        } catch (\Throwable $e) {
            Log::error('GoogleIntegrationController::enviarProcessoParaCalendario - erro', [
                'user_id' => $user->id,
                'processo_id' => $processo->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao enviar licitação para o Google Calendar: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Licitação enviada para o Google Calendar.',
            'data' => $createdEvent,
        ]);
    }

    private function stateCacheKey(string $state): string
    {
        return 'google_oauth_state:'.$state;
    }
}

