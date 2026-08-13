<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Services;

use Hwkdo\BitwardenLaravel\Enums\ManagementApiDriver;
use Hwkdo\IntranetAppBitwarden\Data\AppSettings;
use Hwkdo\IntranetAppBitwarden\Models\IntranetAppBitwardenSettings;

class BitwardenConfigService
{
    /**
     * Aktuelle App-Settings, falls das Bitwarden-App-Package und ein Datensatz vorhanden sind.
     */
    protected function appSettings(): ?AppSettings
    {
        if (! class_exists(IntranetAppBitwardenSettings::class)) {
            return null;
        }

        $current = IntranetAppBitwardenSettings::current();

        if ($current === null || ! $current->settings instanceof AppSettings) {
            return null;
        }

        return $current->settings;
    }

    /**
     * Nicht-leerer String aus App-Settings, sonst null.
     */
    protected function settingString(string $property): ?string
    {
        $settings = $this->appSettings();

        if ($settings === null || ! property_exists($settings, $property)) {
            return null;
        }

        $value = $settings->{$property} ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * Gibt die Bitwarden API URL zurück.
     * App-Settings (wenn gesetzt) vor Config/Env.
     */
    public function getApiUrl(): string
    {
        return $this->settingString('bitwardenApiUrl')
            ?? (string) config('bitwarden-laravel.api_url', '');
    }

    /**
     * Basis-URL ohne trailing slash und ohne /api-Suffix.
     */
    public function getBaseUrl(): string
    {
        $url = $this->getApiUrl();
        $url = rtrim($url, '/');

        if (str_ends_with($url, '/api')) {
            $url = substr($url, 0, -4);
        }

        return rtrim($url, '/');
    }

    /**
     * Gibt den Bitwarden Organization API Token zurück.
     * App-Settings (wenn gesetzt) vor Config/Env.
     */
    public function getOrganizationApiToken(): string
    {
        return $this->settingString('bitwardenOrganizationApiToken')
            ?? (string) config('bitwarden-laravel.organization_api_token', '');
    }

    /**
     * Gibt die Bitwarden Vault API URL zurück.
     * App-Settings (wenn gesetzt) vor Config/Env.
     */
    public function getVaultApiUrl(): string
    {
        return $this->settingString('bitwardenVaultApiUrl')
            ?? (string) config('bitwarden-laravel.vault_api_url', '');
    }

    /**
     * Management-API-Treiber: App-Settings vor Config/Env.
     */
    public function getManagementApiDriver(): ManagementApiDriver
    {
        $settings = $this->appSettings();

        if ($settings !== null) {
            $driver = $settings->managementApiDriver ?? null;

            if ($driver instanceof ManagementApiDriver) {
                return $driver;
            }

            if (is_string($driver) && $driver !== '') {
                return ManagementApiDriver::tryFrom($driver) ?? ManagementApiDriver::Public;
            }
        }

        $fromConfig = (string) config('bitwarden-laravel.management_api_driver', ManagementApiDriver::Public->value);

        return ManagementApiDriver::tryFrom($fromConfig) ?? ManagementApiDriver::Public;
    }

    /**
     * Organization UUID für Native-/Vault-API-Pfade.
     *
     * Reihenfolge: App-Settings → Config/Env → Ableitung aus Client-ID.
     */
    public function getOrganizationId(): string
    {
        $fromSettings = $this->settingString('bitwardenOrganizationId');

        if ($fromSettings !== null) {
            return $fromSettings;
        }

        $fromConfig = config('bitwarden-laravel.organization_id', '');

        if (is_string($fromConfig) && trim($fromConfig) !== '') {
            return trim($fromConfig);
        }

        return $this->organizationIdFromClientId($this->getOrganizationApiClientId());
    }

    /**
     * Organization API Client ID (z. B. organization.{uuid}).
     */
    public function getOrganizationApiClientId(): string
    {
        return $this->settingString('bitwardenOrganizationApiClientId')
            ?? (string) config('bitwarden-laravel.organization_api_client_id', '');
    }

    /**
     * Organization API Client Secret.
     */
    public function getOrganizationApiClientSecret(): string
    {
        return $this->settingString('bitwardenOrganizationApiClientSecret')
            ?? (string) config('bitwarden-laravel.organization_api_client_secret', '');
    }

    /**
     * Extrahiert die Org-UUID aus der Client-ID (ohne "organization."-Präfix).
     */
    public function organizationIdFromClientId(string $clientId): string
    {
        if (str_starts_with($clientId, 'organization.')) {
            return substr($clientId, strlen('organization.'));
        }

        return $clientId;
    }

    public function getAdminToken(): string
    {
        return $this->settingString('bitwardenAdminToken')
            ?? (string) config('bitwarden-laravel.admin_token', '');
    }

    /**
     * User-API-Key Client-ID für den Native-Treiber (nicht organization.*).
     */
    public function getNativeApiClientId(): string
    {
        return $this->settingString('bitwardenNativeApiClientId')
            ?? (string) config('bitwarden-laravel.native_api_client_id', '');
    }

    /**
     * User-API-Key Client-Secret für den Native-Treiber.
     */
    public function getNativeApiClientSecret(): string
    {
        return $this->settingString('bitwardenNativeApiClientSecret')
            ?? (string) config('bitwarden-laravel.native_api_client_secret', '');
    }

    /**
     * @deprecated Settings werden immer bevorzugt, wenn gesetzt. Nur noch für Rückwärtskompatibilität.
     */
    public function shouldUseIntranetAppSettings(): bool
    {
        return true;
    }
}
