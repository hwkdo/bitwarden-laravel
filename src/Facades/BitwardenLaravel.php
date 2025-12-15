<?php

namespace Hwkdo\BitwardenLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\BitwardenLaravel\BitwardenLaravel
 */
class BitwardenLaravel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\BitwardenLaravel\BitwardenLaravel::class;
    }
}
