<?php

namespace Hwkdo\BitwardenLaravel\Services;

use Hwkdo\BitwardenLaravel\Models\BitwardenAccessToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BitwardenTokenService
{
    public function __construct(
        protected BitwardenConfigService $configService
    ) {}

    /**
     * Gibt ein gültiges Access Token zurück.
     * Holt es aus der Datenbank, falls vorhanden und gültig.
     * Generiert ein neues Token, falls keins vorhanden oder abgelaufen.
     */
    public function getToken(): string
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();

        if (empty($clientId) || empty($clientSecret)) {
            throw new \RuntimeException('CLIENT_ID und CLIENT_SECRET müssen gesetzt sein!');
        }

        // Prüfe, ob ein gültiges Token in der DB existiert
        $existingToken = BitwardenAccessToken::getValidTokenForClient($clientId);

        if ($existingToken) {
            return $existingToken->access_token;
        }

        // Generiere neues Token
        return $this->generateAndStoreToken($clientId, $clientSecret);
    }

    /**
     * Generiert ein neues Token und speichert es in der Datenbank.
     */
    protected function generateAndStoreToken(string $clientId, string $clientSecret): string
    {
        $apiUrl = $this->configService->getApiUrl();
        $baseUrl = rtrim($apiUrl, '/api/');
        $tokenUrl = $baseUrl.'/identity/connect/token';

        $deviceId = $this->getDeviceIdentifier();
        $deviceName = config('bitwarden-laravel.organization_api_device_name', 'Public API Client');
        $deviceType = config('bitwarden-laravel.organization_api_device_type', 14);
        $scope = config('bitwarden-laravel.organization_api_scope', 'api.organization');
        $grantType = config('bitwarden-laravel.organization_api_grant_type', 'client_credentials');

        try {
            $response = Http::asForm()->post($tokenUrl, [
                'grant_type' => $grantType,
                'scope' => $scope,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'device_identifier' => $deviceId,
                'device_name' => $deviceName,
                'device_type' => $deviceType,
            ]);

            if (! $response->successful()) {
                Log::error('Bitwarden Token Generation Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException('Fehler beim Abrufen des Tokens: '.$response->body());
            }

            $data = $response->json();

            if (empty($data['access_token'])) {
                throw new \RuntimeException('Kein access_token in der Response erhalten: '.json_encode($data));
            }

            $expiresIn = $data['expires_in'] ?? 3600; // Default: 1 Stunde
            $expiresAt = now()->addSeconds($expiresIn);

            // Speichere Token in der Datenbank
            BitwardenAccessToken::create([
                'client_id' => $clientId,
                'access_token' => $data['access_token'],
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
                'device_identifier' => $deviceId,
                'device_name' => $deviceName,
                'device_type' => $deviceType,
            ]);

            return $data['access_token'];
        } catch (\Exception $e) {
            Log::error('Bitwarden Token Generation Exception', [
                'message' => $e->getMessage(),
                'url' => $tokenUrl,
            ]);

            throw $e;
        }
    }

    /**
     * Gibt die Client ID zurück.
     */
    protected function getClientId(): string
    {
        if ($this->configService->shouldUseIntranetAppSettings()) {
            $settings = \Hwkdo\IntranetAppBitwarden\Models\IntranetAppBitwardenSettings::current();

            if ($settings && $settings->settings) {
                return $settings->settings->bitwardenOrganizationApiClientId ?? '';
            }
        }

        return config('bitwarden-laravel.organization_api_client_id', '');
    }

    /**
     * Gibt das Client Secret zurück.
     */
    protected function getClientSecret(): string
    {
        if ($this->configService->shouldUseIntranetAppSettings()) {
            $settings = \Hwkdo\IntranetAppBitwarden\Models\IntranetAppBitwardenSettings::current();

            if ($settings && $settings->settings) {
                return $settings->settings->bitwardenOrganizationApiClientSecret ?? '';
            }
        }

        return config('bitwarden-laravel.organization_api_client_secret', '');
    }

    /**
     * Gibt die Device Identifier zurück oder generiert einen neuen.
     */
    protected function getDeviceIdentifier(): string
    {
        $deviceId = config('bitwarden-laravel.organization_api_device_identifier');

        if (empty($deviceId)) {
            // Generiere eine UUID-ähnliche ID
            $deviceId = (string) Str::uuid();
            // Optional: In Config speichern für persistente Device ID
        }

        return $deviceId;
    }
}

