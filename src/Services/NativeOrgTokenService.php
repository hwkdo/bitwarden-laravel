<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Services;

use Hwkdo\BitwardenLaravel\Models\BitwardenAccessToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NativeOrgTokenService
{
    public function __construct(
        protected BitwardenConfigService $configService
    ) {}

    public function getToken(): string
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();

        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException(
                'Native-Treiber benötigt einen User-API-Key (bitwardenNativeApiClientId/Secret in den App-Settings oder BITWARDEN_NATIVE_API_CLIENT_ID/SECRET). '
                .'Der Organization-API-Key (organization.*) funktioniert nur mit dem Public-Treiber.'
            );
        }

        if (str_starts_with($clientId, 'organization.')) {
            throw new \RuntimeException(
                'Native-Treiber: client_id darf nicht mit "organization." beginnen. '
                .'Bitte einen User-API-Key eines Org-Owners hinterlegen (Vaultwarden → Konto → API-Key), nicht den Organization-API-Key.'
            );
        }

        $cacheKey = $clientId.'|native';
        $existingToken = BitwardenAccessToken::getValidTokenForClient($cacheKey);

        if ($existingToken) {
            return $existingToken->access_token;
        }

        return $this->generateAndStoreToken($clientId, $clientSecret, $cacheKey);
    }

    protected function generateAndStoreToken(string $clientId, string $clientSecret, string $cacheKey): string
    {
        $tokenUrl = $this->configService->getBaseUrl().'/identity/connect/token';

        $deviceId = $this->getDeviceIdentifier();
        $deviceName = config('bitwarden-laravel.native_api_device_name', 'Native Management API Client');
        $deviceType = (string) config('bitwarden-laravel.native_api_device_type', 100);
        $scope = config('bitwarden-laravel.native_api_scope', 'api');
        $grantType = config('bitwarden-laravel.organization_api_grant_type', 'client_credentials');

        try {
            $response = Http::asForm()->post($tokenUrl, [
                'grant_type' => $grantType,
                'scope' => $scope,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'deviceIdentifier' => $deviceId,
                'deviceName' => $deviceName,
                'deviceType' => $deviceType,
            ]);

            if (! $response->successful()) {
                Log::error('Bitwarden Native Token Generation Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException('Fehler beim Abrufen des Native-Tokens: '.$response->body());
            }

            $data = $response->json();

            if (empty($data['access_token'])) {
                throw new \RuntimeException('Kein access_token in der Response erhalten: '.json_encode($data));
            }

            $expiresIn = $data['expires_in'] ?? 3600;
            $expiresAt = now()->addSeconds($expiresIn);

            BitwardenAccessToken::create([
                'client_id' => $cacheKey,
                'access_token' => $data['access_token'],
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
                'device_identifier' => $deviceId,
                'device_name' => $deviceName,
                'device_type' => (int) $deviceType,
            ]);

            return $data['access_token'];
        } catch (\Exception $e) {
            Log::error('Bitwarden Native Token Generation Exception', [
                'message' => $e->getMessage(),
                'url' => $tokenUrl,
            ]);

            throw $e;
        }
    }

    protected function getClientId(): string
    {
        return $this->configService->getNativeApiClientId();
    }

    protected function getClientSecret(): string
    {
        return $this->configService->getNativeApiClientSecret();
    }

    protected function getDeviceIdentifier(): string
    {
        $deviceId = config('bitwarden-laravel.native_api_device_identifier');

        if (empty($deviceId)) {
            $deviceId = config('bitwarden-laravel.organization_api_device_identifier');
        }

        if (empty($deviceId)) {
            $deviceId = (string) Str::uuid();
        }

        return $deviceId;
    }
}
