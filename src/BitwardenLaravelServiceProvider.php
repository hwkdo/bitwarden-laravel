<?php

namespace Hwkdo\BitwardenLaravel;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Hwkdo\BitwardenLaravel\Commands\BitwardenLaravelCommand;

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
            ->hasCommand(BitwardenLaravelCommand::class);
    }
}
