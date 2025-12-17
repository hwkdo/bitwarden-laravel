<?php

namespace Hwkdo\BitwardenLaravel;

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
        $this->app->singleton(\Hwkdo\BitwardenLaravel\Services\BitwardenTokenService::class, function ($app) {
            return new \Hwkdo\BitwardenLaravel\Services\BitwardenTokenService(
                $app->make(\Hwkdo\BitwardenLaravel\Services\BitwardenConfigService::class)
            );
        });

        $this->app->singleton(\Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService::class, function ($app) {
            return new \Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService(
                $app->make(\Hwkdo\BitwardenLaravel\Services\BitwardenConfigService::class),
                $app->make(\Hwkdo\BitwardenLaravel\Services\BitwardenTokenService::class)
            );
        });

        $this->app->singleton(\Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService::class, function ($app) {
            return new \Hwkdo\BitwardenLaravel\Services\BitwardenVaultApiService(
                $app->make(\Hwkdo\BitwardenLaravel\Services\BitwardenConfigService::class)
            );
        });
    }
}
