<?php

namespace Hwkdo\BitwardenLaravel\Commands;

use Illuminate\Console\Command;

class BitwardenLaravelCommand extends Command
{
    public $signature = 'bitwarden-laravel';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
