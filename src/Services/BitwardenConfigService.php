<?php

namespace Hwkdo\BitwardenLaravel\Services;

use Hwkdo\IntranetAppBitwarden\Models\IntranetAppBitwardenSettings;

class BitwardenConfigService
{
    /**
     * Gibt die Bitwarden API URL zurück.
     * Entweder aus der Config oder aus IntranetAppBitwardenSettings.
     */
    public function getApiUrl(): string
    {
        if ($this->shouldUseIntranetAppSettings()) {
            $settings = IntranetAppBitwardenSettings::current();
            
            if ($settings && $settings->settings) {
                return $settings->settings->bitwardenApiUrl;
            }
        }

        return config('bitwarden-laravel.api_url', '');
    }

    /**
     * Gibt den Bitwarden Organization API Token zurück.
     * Entweder aus der Config oder aus IntranetAppBitwardenSettings.
     */
    public function getOrganizationApiToken(): string
    {
        if ($this->shouldUseIntranetAppSettings()) {
            $settings = IntranetAppBitwardenSettings::current();
            
            if ($settings && $settings->settings) {
                return $settings->settings->bitwardenOrganizationApiToken;
            }
        }

        return config('bitwarden-laravel.organization_api_token', '');
    }

    /**
     * Gibt die Bitwarden Vault API URL zurück.
     * Entweder aus der Config oder aus IntranetAppBitwardenSettings.
     */
    public function getVaultApiUrl(): string
    {
        if ($this->shouldUseIntranetAppSettings()) {
            $settings = IntranetAppBitwardenSettings::current();

            if ($settings && $settings->settings) {
                return $settings->settings->bitwardenVaultApiUrl ?? '';
            }
        }

        return config('bitwarden-laravel.vault_api_url', '');
    }

    /**
     * Prüft, ob die Intranet App Settings verwendet werden sollen.
     */
    public function shouldUseIntranetAppSettings(): bool
    {
        return config('bitwarden-laravel.use_intranet_app_settings', false);
    }
}

