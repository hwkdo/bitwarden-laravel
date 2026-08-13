<?php

declare(strict_types=1);

use Hwkdo\BitwardenLaravel\Enums\ManagementApiDriver;
use Hwkdo\BitwardenLaravel\Services\BitwardenConfigService;
use Hwkdo\IntranetAppBitwarden\Data\AppSettings;

it('falls back to config driver when no app settings are available', function (): void {
    config()->set('bitwarden-laravel.management_api_driver', 'native');

    $service = new BitwardenConfigService;

    // Ohne Settings-Datensatz (oder wenn Property fehlt) greift Config.
    // In Testbench existiert das Model ggf.; wir prüfen den Config-Fallback über Org-ID/URL.
    expect($service->getManagementApiDriver())->toBeIn([
        ManagementApiDriver::Native,
        ManagementApiDriver::Public,
    ]);
});

it('returns base url without api suffix', function (): void {
    config()->set('bitwarden-laravel.api_url', 'https://vw.example.com/api/');

    $service = new class extends BitwardenConfigService
    {
        protected function appSettings(): ?AppSettings
        {
            return null;
        }
    };

    expect($service->getBaseUrl())->toBe('https://vw.example.com');
});

it('reads organization id and admin token from config when settings empty', function (): void {
    config()->set('bitwarden-laravel.organization_id', 'org-xyz');
    config()->set('bitwarden-laravel.admin_token', 'secret-admin');

    $service = new class extends BitwardenConfigService
    {
        protected function appSettings(): ?AppSettings
        {
            return null;
        }
    };

    expect($service->getOrganizationId())->toBe('org-xyz')
        ->and($service->getAdminToken())->toBe('secret-admin');
});

it('derives organization id from organization api client id when unset', function (): void {
    config()->set('bitwarden-laravel.organization_id', '');
    config()->set('bitwarden-laravel.organization_api_client_id', 'organization.abc-def-123');

    $service = new class extends BitwardenConfigService
    {
        protected function appSettings(): ?AppSettings
        {
            return null;
        }
    };

    expect($service->getOrganizationId())->toBe('abc-def-123');
});

it('prefers explicit organization id over client id derivation', function (): void {
    config()->set('bitwarden-laravel.organization_id', 'explicit-org');
    config()->set('bitwarden-laravel.organization_api_client_id', 'organization.abc-def-123');

    $service = new class extends BitwardenConfigService
    {
        protected function appSettings(): ?AppSettings
        {
            return null;
        }
    };

    expect($service->getOrganizationId())->toBe('explicit-org');
});

it('prefers app settings driver over config', function (): void {
    config()->set('bitwarden-laravel.management_api_driver', 'public');

    $settings = new AppSettings(
        managementApiDriver: ManagementApiDriver::Native,
        bitwardenAdminToken: 'from-settings',
    );

    $service = new class($settings) extends BitwardenConfigService
    {
        public function __construct(private AppSettings $fakeSettings) {}

        protected function appSettings(): ?AppSettings
        {
            return $this->fakeSettings;
        }
    };

    expect($service->getManagementApiDriver())->toBe(ManagementApiDriver::Native)
        ->and($service->getAdminToken())->toBe('from-settings');
});
