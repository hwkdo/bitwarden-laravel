<?php

// config for Hwkdo/BitwardenLaravel
return [
    /*
    |--------------------------------------------------------------------------
    | Use Intranet App Bitwarden Settings
    |--------------------------------------------------------------------------
    |
    | Veraltet: App-Settings werden immer genutzt, wenn ein Wert gesetzt ist;
    | sonst greifen die Config-/ENV-Defaults. Dieser Schalter wird ignoriert.
    |
    */
    'use_intranet_app_settings' => env('BITWARDEN_USE_INTRANET_APP_SETTINGS', true),

    /*
    |--------------------------------------------------------------------------
    | Management API Driver
    |--------------------------------------------------------------------------
    |
    | public = Organization Public API (Vaultwarden-Fork)
    | native = Stock-Vaultwarden Org-/Admin-API (Spec)
    |
    */
    'management_api_driver' => env('BITWARDEN_MANAGEMENT_API_DRIVER', 'public'),

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
    | Organization ID
    |--------------------------------------------------------------------------
    |
    | Optional. Wird vom Native-Treiber und der Vault-API genutzt.
    | Leer = Ableitung aus organization_api_client_id (Präfix "organization." entfernen).
    |
    */
    'organization_id' => env('BITWARDEN_ORGANIZATION_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Admin Token (Native Driver – Vaultwarden Admin Panel)
    |--------------------------------------------------------------------------
    */
    'admin_token' => env('BITWARDEN_ADMIN_TOKEN', ''),

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
    | Native Management API Credentials
    |--------------------------------------------------------------------------
    |
    | User-API-Key eines Org-Owners (NICHT organization.{uuid} – das ist nur
    | für den Public-API-Treiber). Scope typischerweise "api".
    |
    */
    'native_api_client_id' => env('BITWARDEN_NATIVE_API_CLIENT_ID', ''),
    'native_api_client_secret' => env('BITWARDEN_NATIVE_API_CLIENT_SECRET', ''),
    'native_api_scope' => env('BITWARDEN_NATIVE_API_SCOPE', 'api'),
    'native_api_device_name' => env('BITWARDEN_NATIVE_API_DEVICE_NAME', 'Native Management API Client'),
    'native_api_device_type' => env('BITWARDEN_NATIVE_API_DEVICE_TYPE', 100),
    'native_api_device_identifier' => env('BITWARDEN_NATIVE_API_DEVICE_IDENTIFIER', ''),

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
