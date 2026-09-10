<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vaultwarden Admin-Panel API (Cookie-Auth via Admin-Token).
 *
 * Löscht echte Benutzerkonten — nicht nur Org-Mitgliedschaften.
 */
class VaultwardenAdminApiService
{
    public function __construct(
        protected BitwardenConfigService $configService,
        protected VaultwardenAdminSession $adminSession,
    ) {}

    /**
     * Löscht das Vaultwarden-Benutzerkonto vollständig (inkl. aller Org-Mitgliedschaften).
     *
     * @param  string  $userId  Globale User-ID (Member-Feld userId), nicht die Organization-User-ID
     */
    public function deleteUserAccount(string $userId): void
    {
        $userId = trim($userId);

        if ($userId === '') {
            throw new \InvalidArgumentException('User-ID darf nicht leer sein.');
        }

        $this->makeAdminRequest('POST', "/admin/users/{$userId}/delete");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function makeAdminRequest(string $method, string $endpoint, array $data = []): array
    {
        $baseUrl = $this->configService->getBaseUrl();
        $url = $baseUrl.'/'.ltrim($endpoint, '/');

        try {
            $response = $this->send($method, $url, $data, $this->adminSession->getCookie());

            // Abgelaufene Session: Cookie verwerfen, neu einloggen, einmal retry
            if (in_array($response->status(), [401, 403], true)) {
                $this->adminSession->clear();
                $response = $this->send($method, $url, $data, $this->adminSession->getCookie());
            }

            if (! $response->successful()) {
                Log::error('Vaultwarden Admin API Request Failed', [
                    'method' => $method,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException(
                    "Fehler bei der Vaultwarden-Admin-API: {$response->status()} - {$response->body()}"
                );
            }

            $json = $response->json();

            return is_array($json) ? $json : [];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Vaultwarden Admin API Request Exception', [
                'method' => $method,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function send(string $method, string $url, array $data, string $cookie): \Illuminate\Http\Client\Response
    {
        $baseUrl = $this->configService->getBaseUrl();

        $request = Http::acceptJson()
            ->timeout(30)
            ->withHeaders([
                'Cookie' => $cookie,
                'Origin' => $baseUrl,
                'Referer' => $baseUrl.'/admin/users/overview',
            ])
            ->asJson();

        $payload = $data === [] ? new \stdClass : $data;

        return match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $payload),
            'PUT' => $request->put($url, $payload),
            'DELETE' => $request->delete($url),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }
}
