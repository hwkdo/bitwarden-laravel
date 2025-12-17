<?php

// config for Hwkdo/BitwardenLaravel
return [
    /*
    |--------------------------------------------------------------------------
    | Use Intranet App Bitwarden Settings
    |--------------------------------------------------------------------------
    |
    | Wenn diese Option auf true gesetzt ist, werden die URL und der Token
    | aus dem IntranetAppBitwardenSettings Model verwendet statt aus den
    | ENV-Variablen.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Bitwarden API URL
    |--------------------------------------------------------------------------
    |
    | Die URL der Bitwarden API. Wird nur verwendet, wenn
    | use_intranet_app_settings auf false steht.
    |
    */
    'api_url' => env('BITWARDEN_API_URL', 'https://vaultwarden-for-all.swarm.hwkdo.com/api/'),

    /*
    |--------------------------------------------------------------------------
    | Bitwarden Organization API Token
    |--------------------------------------------------------------------------
    |
    | Der API Token für die Bitwarden Organization. Wird nur verwendet, wenn
    | use_intranet_app_settings auf false steht.
    |
    */
    'organization_api_client_id' => env('BITWARDEN_ORGANIZATION_API_CLIENT_ID', ''),
    'organization_api_client_secret' => env('BITWARDEN_ORGANIZATION_API_CLIENT_SECRET', ''),
    'organization_api_scope' => env('BITWARDEN_ORGANIZATION_API_SCOPE', 'api.organization'),
    'organization_api_grant_type' => env('BITWARDEN_ORGANIZATION_API_GRANT_TYPE', 'client_credentials'),
    'organization_api_device_identifier' => env('BITWARDEN_ORGANIZATION_API_DEVICE_IDENTIFIER', ''),
    'organization_api_device_name' => env('BITWARDEN_ORGANIZATION_API_DEVICE_NAME', 'Public API Client'),
    'organization_api_device_type' => env('BITWARDEN_ORGANIZATION_API_DEVICE_TYPE', 14),

    /*
    |--------------------------------------------------------------------------
    | Bitwarden Vault API Configuration
    |--------------------------------------------------------------------------
    |
    | Die Vault API wird für administrative Aufgaben wie das Erstellen von
    | Collections verwendet. Sie benötigt separate Credentials.
    |
    */
    'vault_api_url' => env('BITWARDEN_VAULT_API_URL', ''),    
    'vault_password' => env('BITWARDEN_VAULT_API_PASSWORD', ''),
];
