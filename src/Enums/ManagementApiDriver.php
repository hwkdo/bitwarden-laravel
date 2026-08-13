<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Enums;

enum ManagementApiDriver: string
{
    case Public = 'public';
    case Native = 'native';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Public->value => 'Public API (Vaultwarden-Fork)',
            self::Native->value => 'Native (Stock-Vaultwarden / Spec)',
        ];
    }
}
