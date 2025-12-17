<?php

namespace Hwkdo\BitwardenLaravel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitwardenVaultApiService
{
    public function __construct(
        protected BitwardenConfigService $configService
    ) {}

    /**
     * Gibt die Basis-URL für die Vault API zurück.
     */
    protected function getApiBaseUrl(): string
    {
        $apiUrl = $this->configService->getVaultApiUrl();

        // Stelle sicher, dass die URL mit / endet (Vault API benötigt kein /api/ Präfix)
        if (! str_ends_with($apiUrl, '/')) {
            $apiUrl = $apiUrl.'/';
        }

        return $apiUrl;
    }

    /**
     * Gibt die Organization ID zurück.
     * Extrahiert die ID aus dem organization_api_client_id (ohne "organization." Präfix).
     */
    protected function getOrganizationId(): string
    {
        $clientId = config('bitwarden-laravel.organization_api_client_id', '');

        // Entferne das "organization." Präfix falls vorhanden
        if (str_starts_with($clientId, 'organization.')) {
            return substr($clientId, strlen('organization.'));
        }

        return $clientId;
    }

    /**
     * Führt eine HTTP-Anfrage an die Bitwarden Vault API aus.
     * Die Vault API benötigt keine Authentifizierung.
     *
     * @param  string  $method
     * @param  string  $endpoint
     * @param  array  $data  Body-Daten für POST/PUT/PATCH
     * @param  array  $queryParams  Query-Parameter für GET/DELETE
     */
    protected function makeRequest(string $method, string $endpoint, array $data = [], array $queryParams = []): array
    {
        $url = $this->getApiBaseUrl().ltrim($endpoint, '/');

        try {
            // Vault API benötigt keine Authentifizierung
            $request = Http::acceptJson();

            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $request = $request->asJson();
            }

            // Bei GET/DELETE werden Query-Parameter als zweites Argument übergeben
            // Bei POST/PUT/PATCH werden sie in die URL eingebaut
            if (in_array($method, ['GET', 'DELETE'])) {
                $response = $request->{strtolower($method)}($url, $queryParams);
            } else {
                // Füge Query-Parameter zur URL hinzu für POST/PUT/PATCH
                if (! empty($queryParams)) {
                    $url .= '?'.http_build_query($queryParams);
                }
                $response = $request->{strtolower($method)}($url, $data);
            }

            if (! $response->successful()) {
                Log::error('Bitwarden Vault API Request Failed', [
                    'method' => $method,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException(
                    "Fehler bei der Vault API-Anfrage: {$response->status()} - {$response->body()}"
                );
            }

            $jsonResponse = $response->json();

            // Stelle sicher, dass wir immer ein Array zurückgeben
            if ($jsonResponse === null) {
                $jsonResponse = [];
            }

            return $jsonResponse;
        } catch (\Exception $e) {
            Log::error('Bitwarden Vault API Request Exception', [
                'method' => $method,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ==================== Collections-Endpunkte (Vault API) ====================

    /**
     * Erstellt eine neue Collection über die Vault API.
     *
     * @param  array{name: string, externalId?: string, groups?: array, users?: array}  $data
     * @param  string|null  $organizationId  Optional, wird automatisch aus Config geholt wenn nicht angegeben
     */
    public function createCollection(array $data, ?string $organizationId = null): array
    {
        $orgId = $organizationId ?? $this->getOrganizationId();
        
        // organizationId muss sowohl im Body als auch als Query-Parameter vorhanden sein
        $data['organizationId'] = $orgId;

        return $this->makeRequest('POST', '/object/org-collection', $data, [
            'organizationid' => $orgId, // Query-Parameter ist lowercase
        ]);
    }

    /**
     * Aktualisiert eine bestehende Collection über die Vault API.
     *
     * @param  string  $collectionId
     * @param  array{name?: string, externalId?: string, groups?: array, users?: array}  $data
     * @param  string|null  $organizationId  Optional, wird automatisch aus Config geholt wenn nicht angegeben
     */
    public function updateCollection(string $collectionId, array $data, ?string $organizationId = null): array
    {
        $orgId = $organizationId ?? $this->getOrganizationId();
        
        // organizationId muss sowohl im Body als auch als Query-Parameter vorhanden sein
        $data['organizationId'] = $orgId;

        return $this->makeRequest('PUT', "/object/org-collection/{$collectionId}", $data, [
            'organizationid' => $orgId, // Query-Parameter ist lowercase
        ]);
    }

    /**
     * Ruft eine einzelne Collection über die Vault API ab.
     *
     * @param  string  $collectionId
     * @param  string|null  $organizationId  Optional, wird automatisch aus Config geholt wenn nicht angegeben
     */
    public function getCollection(string $collectionId, ?string $organizationId = null): array
    {
        $orgId = $organizationId ?? $this->getOrganizationId();

        return $this->makeRequest('GET', "/object/org-collection/{$collectionId}", [], [
            'organizationId' => $orgId,
        ]);
    }

    /**
     * Löscht eine Collection über die Vault API.
     *
     * @param  string  $collectionId
     * @param  string|null  $organizationId  Optional, wird automatisch aus Config geholt wenn nicht angegeben
     */
    public function deleteCollection(string $collectionId, ?string $organizationId = null): void
    {
        $orgId = $organizationId ?? $this->getOrganizationId();

        $this->makeRequest('DELETE', "/object/org-collection/{$collectionId}", [], [
            'organizationId' => $orgId,
        ]);
    }

    /**
     * Ruft alle Collections der Organisation über die Vault API ab.
     *
     * @param  string|null  $organizationId  Optional, wird automatisch aus Config geholt wenn nicht angegeben
     */
    public function listOrgCollections(?string $organizationId = null): array
    {
        $orgId = $organizationId ?? $this->getOrganizationId();

        return $this->makeRequest('GET', '/list/object/org-collections', [], [
            'organizationId' => $orgId,
        ]);
    }

    /**
     * Ruft alle Collections  über die Vault API ab.
     *
     * @param  string|null  $organizationId  Optional, wird automatisch aus Config geholt wenn nicht angegeben
     */
    public function listCollections(): array
    {
        return $this->makeRequest('GET', '/list/object/collections');
    }

    // ==================== Members-Endpunkte (Vault API) ====================

    /**
     * Ruft alle Mitglieder der Organisation über die Vault API ab.
     *
     * @param  string|null  $organizationId  Optional, wird automatisch aus Config geholt wenn nicht angegeben
     */
    public function listMembers(?string $organizationId = null): array
    {
        $orgId = $organizationId ?? $this->getOrganizationId();

        return $this->makeRequest('GET', '/list/object/org-members', [], [
            'organizationId' => $orgId,
        ]);
    }

    /**
     * Bestätigt ein Organisationsmitglied über die Vault API.
     *
     * @param  string  $memberId
     * @param  string|null  $organizationId  Optional, wird automatisch aus Config geholt wenn nicht angegeben
     */
    public function confirmMember(string $memberId, ?string $organizationId = null): array
    {
        $orgId = $organizationId ?? $this->getOrganizationId();

        return $this->makeRequest('POST', "/confirm/org-member/{$memberId}", [
            'organizationId' => $orgId,
        ]);
    }

    // ==================== Vault Management-Endpunkte ====================

    /**
     * Ruft den Status des Vault-Servers ab.
     * Gibt Informationen über den Server-Status zurück.
     */
    public function getStatus(): array
    {
        return $this->makeRequest('GET', '/status');
    }

    /**
     * Synchronisiert den Vault.
     * Aktualisiert die lokalen Daten mit den neuesten Daten vom Server.
     */
    public function sync(): array
    {
        return $this->makeRequest('POST', '/sync');
    }

    /**
     * Sperrt den Vault.
     * Der Vault muss vor dem Zugriff auf Daten wieder entsperrt werden.
     */
    public function lock(): array
    {
        return $this->makeRequest('POST', '/lock');
    }

    /**
     * Entsperrt den Vault.
     * Erfordert das Master-Passwort.
     *
     * @param  string|null  $password  Das Master-Passwort zum Entsperren. Wenn nicht angegeben, wird das Passwort aus der Config verwendet.
     */
    public function unlock(?string $password = null): array
    {
        $password = $password ?? config('bitwarden-laravel.vault_password');

        if (empty($password)) {
            throw new \RuntimeException('Vault-Passwort ist nicht gesetzt. Bitte in der Config setzen oder als Parameter übergeben.');
        }

        return $this->makeRequest('POST', '/unlock', [
            'password' => $password,
        ]);
    }
}

