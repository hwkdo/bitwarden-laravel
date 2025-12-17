<?php

namespace Hwkdo\BitwardenLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class BitwardenAccessToken extends Model
{
    protected $fillable = [
        'client_id',
        'access_token',
        'expires_in',
        'expires_at',
        'device_identifier',
        'device_name',
        'device_type',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'expires_in' => 'integer',
        'device_type' => 'integer',
    ];

    /**
     * Prüft, ob das Token noch gültig ist.
     */
    public function isValid(): bool
    {
        return $this->expires_at && $this->expires_at->isFuture();
    }

    /**
     * Gibt das Token für einen bestimmten Client zurück, falls gültig.
     */
    public static function getValidTokenForClient(string $clientId): ?self
    {
        return static::where('client_id', $clientId)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();
    }
}

