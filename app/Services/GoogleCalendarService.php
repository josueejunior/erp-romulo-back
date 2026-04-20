<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserGoogleIntegration;
use App\Modules\Auth\Models\User;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use RuntimeException;

class GoogleCalendarService
{
    /**
     * @return array{auth_url: string, state: string}
     */
    public function buildAuthorizationUrl(string $state): array
    {
        $client = $this->makeClient();
        $client->setState($state);

        return [
            'auth_url' => $client->createAuthUrl(),
            'state' => $state,
        ];
    }

    public function handleOAuthCallback(User $user, string $code): UserGoogleIntegration
    {
        $client = $this->makeClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException('Falha ao autenticar com o Google: '.$token['error']);
        }

        $client->setAccessToken($token);

        $calendarService = new Calendar($client);
        $calendar = $calendarService->calendarList->get('primary');

        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Google não retornou access_token.');
        }

        $refreshToken = $token['refresh_token'] ?? null;
        $expiresIn = (int) ($token['expires_in'] ?? 3600);
        $scopes = $token['scope'] ?? '';

        $integration = UserGoogleIntegration::query()->firstOrNew(['user_id' => $user->id]);
        $integration->google_email = $calendar->getSummary() ?: $user->email;
        $integration->access_token = $accessToken;
        $integration->token_expires_at = Carbon::now()->addSeconds($expiresIn);
        $integration->scopes = is_string($scopes) ? preg_split('/\s+/', trim($scopes)) : [];
        $integration->calendar_id = 'primary';
        $integration->connected_at = Carbon::now();

        if (is_string($refreshToken) && $refreshToken !== '') {
            $integration->refresh_token = $refreshToken;
        }

        $integration->save();

        return $integration;
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     * @return array<string, mixed>
     */
    public function createEventForUser(User $user, array $eventPayload): array
    {
        $integration = UserGoogleIntegration::query()->where('user_id', $user->id)->first();

        if (!$integration) {
            throw new RuntimeException('Integração Google não conectada para este usuário.');
        }

        $client = $this->makeClient();
        $expiresAt = $integration->token_expires_at instanceof Carbon
            ? $integration->token_expires_at
            : Carbon::now()->subMinute();

        $client->setAccessToken([
            'access_token' => $integration->access_token,
            'refresh_token' => $integration->refresh_token,
            'expires_in' => max(60, Carbon::now()->diffInSeconds($expiresAt, false)),
            'created' => Carbon::now()->timestamp,
        ]);

        if ($client->isAccessTokenExpired()) {
            if (!$integration->refresh_token) {
                throw new RuntimeException('Refresh token ausente. Reconecte sua conta Google.');
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($integration->refresh_token);
            if (isset($newToken['error'])) {
                throw new RuntimeException('Falha ao renovar token Google: '.$newToken['error']);
            }

            if (!empty($newToken['access_token'])) {
                $integration->access_token = $newToken['access_token'];
            }

            if (!empty($newToken['refresh_token'])) {
                $integration->refresh_token = $newToken['refresh_token'];
            }

            if (!empty($newToken['expires_in'])) {
                $integration->token_expires_at = Carbon::now()->addSeconds((int) $newToken['expires_in']);
            }

            $integration->save();
            $client->setAccessToken([
                'access_token' => $integration->access_token,
                'refresh_token' => $integration->refresh_token,
            ]);
        }

        $calendarService = new Calendar($client);
        $createdEvent = $calendarService->events->insert(
            $integration->calendar_id ?: 'primary',
            new GoogleCalendarEvent($eventPayload),
            ['sendUpdates' => 'none']
        );

        return [
            'id' => $createdEvent->getId(),
            'htmlLink' => $createdEvent->getHtmlLink(),
            'status' => $createdEvent->getStatus(),
        ];
    }

    private function makeClient(): GoogleClient
    {
        $clientId = (string) config('google.client_id');
        $clientSecret = (string) config('google.client_secret');
        $redirectUri = (string) config('google.redirect_uri');

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('Credenciais Google não configuradas no ambiente.');
        }

        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->setPrompt('consent');
        $client->setScopes([
            Calendar::CALENDAR,
        ]);

        return $client;
    }
}

