<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel;

use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Drivers\ManagementApiDriverProxy;
use Hwkdo\BitwardenLaravel\Drivers\NativeVaultwardenManagementApiDriver;
use Hwkdo\BitwardenLaravel\Drivers\PublicApiManagementApiDriver;
use Hwkdo\BitwardenLaravel\Services\BitwardenConfigService;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;
use Hwkdo\BitwardenLaravel\Services\BitwardenTokenService;
use Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService;
use Hwkdo\BitwardenLaravel\Services\NativeOrgTokenService;
use Hwkdo\BitwardenLaravel\Services\VaultwardenAdminSession;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BitwardenLaravelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('bitwarden-laravel')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_bitwarden_laravel_table')
            ->hasMigration('create_bitwarden_access_tokens_table');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(BitwardenConfigService::class);

        $this->app->singleton(BitwardenTokenService::class, function ($app) {
            return new BitwardenTokenService(
                $app->make(BitwardenConfigService::class)
            );
        });

        $this->app->singleton(NativeOrgTokenService::class, function ($app) {
            return new NativeOrgTokenService(
                $app->make(BitwardenConfigService::class)
            );
        });

        $this->app->singleton(VaultwardenAdminSession::class, function ($app) {
            return new VaultwardenAdminSession(
                $app->make(BitwardenConfigService::class)
            );
        });

        $this->app->singleton(PublicApiManagementApiDriver::class, function ($app) {
            return new PublicApiManagementApiDriver(
                $app->make(BitwardenConfigService::class),
                $app->make(BitwardenTokenService::class)
            );
        });

        $this->app->singleton(NativeVaultwardenManagementApiDriver::class, function ($app) {
            return new NativeVaultwardenManagementApiDriver(
                $app->make(BitwardenConfigService::class),
                $app->make(NativeOrgTokenService::class),
            );
        });

        $this->app->singleton(ManagementApiDriverProxy::class, function ($app) {
            return new ManagementApiDriverProxy(
                $app,
                $app->make(BitwardenConfigService::class)
            );
        });

        $this->app->singleton(BitwardenManagementApiInterface::class, function ($app) {
            return $app->make(ManagementApiDriverProxy::class);
        });

        // BC: alte Type-Hints bekommen weiterhin den Public-Treiber
        $this->app->singleton(BitwardenPublicApiService::class, function ($app) {
            return new BitwardenPublicApiService(
                $app->make(BitwardenConfigService::class),
                $app->make(BitwardenTokenService::class)
            );
        });

        $this->app->singleton(BitwardenVaultApiService::class, function ($app) {
            return new BitwardenVaultApiService(
                $app->make(BitwardenConfigService::class)
            );
        });
    }
}
