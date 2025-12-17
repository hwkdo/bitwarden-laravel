<?php

namespace Hwkdo\BitwardenLaravel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitwardenPublicApiService
{
    public function __construct(
        protected BitwardenConfigService $configService,
        protected BitwardenTokenService $tokenService
    ) {}

    /**
     * Gibt die Basis-URL für die API zurück.
     */
    protected function getApiBaseUrl(): string
    {
        $apiUrl = $this->configService->getApiUrl();
        
        // Stelle sicher, dass die URL mit /api/ endet
        if (! str_ends_with($apiUrl, '/api/')) {
            $apiUrl = rtrim($apiUrl, '/').'/api/';
        }

        return $apiUrl;
    }

    /**
     * Führt eine HTTP-Anfrage an die Bitwarden API aus.
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->tokenService->getToken();
        $url = $this->getApiBaseUrl().ltrim($endpoint, '/');

        try {
            $request = Http::withToken($token);

            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $request = $request->asJson();
            }

            $response = $request->{strtolower($method)}($url, $data);

            if (! $response->successful()) {
                Log::error('Bitwarden Public API Request Failed', [
                    'method' => $method,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException(
                    "Fehler bei der API-Anfrage: {$response->status()} - {$response->body()}"
                );
            }

            $jsonResponse = $response->json();
            
            // Stelle sicher, dass wir immer ein Array zurückgeben
            if ($jsonResponse === null) {
                $jsonResponse = [];
            }
            
            // Log für Debugging
            if ($method === 'GET' && in_array('groups', explode('/', $endpoint))) {
                Log::debug('Bitwarden Groups API Response', [
                    'endpoint' => $endpoint,
                    'response_type' => gettype($jsonResponse),
                    'response_count' => is_array($jsonResponse) ? count($jsonResponse) : 'N/A',
                    'response_keys' => is_array($jsonResponse) ? array_keys($jsonResponse) : 'N/A',
                    'first_item' => is_array($jsonResponse) && ! empty($jsonResponse) ? ($jsonResponse[0] ?? $jsonResponse) : null,
                ]);
            }
            
            // Log für PUT-Requests (z.B. updateGroupUsers)
            if ($method === 'PUT' && str_contains($endpoint, '/users')) {
                Log::debug('Bitwarden Update Group Users Request', [
                    'endpoint' => $endpoint,
                    'data_sent' => $data,
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                    'response_json' => $jsonResponse,
                ]);
            }

            return $jsonResponse;
        } catch (\Exception $e) {
            Log::error('Bitwarden Public API Request Exception', [
                'method' => $method,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ==================== Gruppen-Endpunkte ====================

    /**
     * Ruft alle Gruppen ab.
     */
    public function getGroups(): array
    {
        $response = $this->makeRequest('GET', '/public/groups');
        
        // Die API gibt die Gruppen in einem 'data' Array zurück
        if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        
        return $response;
    }

    /**
     * Ruft alle Gruppen mit Details ab.
     */
    public function getGroupsWithDetails(): array
    {
        return $this->makeRequest('GET', '/public/groups/details');
    }

    /**
     * Ruft eine einzelne Gruppe ab.
     */
    public function getGroup(string $groupId): array
    {
        return $this->makeRequest('GET', "/public/groups/{$groupId}");
    }

    /**
     * Erstellt eine neue Gruppe.
     *
     * @param  array{name: string, accessAll: bool, collections: array, users: array}  $data
     */
    public function createGroup(array $data): array
    {
        return $this->makeRequest('POST', '/public/groups', $data);
    }

    /**
     * Aktualisiert eine bestehende Gruppe.
     *
     * @param  string  $groupId
     * @param  array{name?: string, accessAll?: bool, collections?: array, users?: array}  $data
     */
    public function updateGroup(string $groupId, array $data): array
    {
        return $this->makeRequest('PUT', "/public/groups/{$groupId}", $data);
    }

    /**
     * Löscht eine Gruppe.
     */
    public function deleteGroup(string $groupId): void
    {
        $this->makeRequest('DELETE', "/public/groups/{$groupId}");
    }

    /**
     * Ruft die Mitglieder einer Gruppe ab.
     */
    public function getGroupUsers(string $groupId): array
    {
        return $this->makeRequest('GET', "/public/groups/{$groupId}/users");
    }

    /**
     * Aktualisiert die Mitglieder einer Gruppe.
     *
     * @param  string  $groupId
     * @param  array<string>  $userIds
     */
    public function updateGroupUsers(string $groupId, array $userIds): array
    {
        // Die Bitwarden API erwartet möglicherweise ein Objekt mit einem 'userIds' Schlüssel
        // oder direkt ein Array. Versuche zuerst das direkte Array-Format.
        // Falls das nicht funktioniert, können wir es auf {"userIds": [...]} ändern.
        return $this->makeRequest('PUT', "/public/groups/{$groupId}/users", $userIds);
    }

    // ==================== Mitglieder-Endpunkte ====================

    /**
     * Ruft alle Mitglieder ab.
     *
     * @param  bool  $includeCollections
     * @param  bool  $includeGroups
     */
    public function getMembers(bool $includeCollections = false, bool $includeGroups = false): array
    {
        $queryParams = [];

        if ($includeCollections) {
            $queryParams['includeCollections'] = 'true';
        }

        if ($includeGroups) {
            $queryParams['includeGroups'] = 'true';
        }

        $endpoint = '/public/members';
        if (! empty($queryParams)) {
            $endpoint .= '?'.http_build_query($queryParams);
        }

        $response = $this->makeRequest('GET', $endpoint);
        
        // Die API gibt die Mitglieder in einem 'data' Array zurück
        if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        
        return $response;
    }

    /**
     * Ruft ein einzelnes Mitglied ab.
     *
     * @param  string  $memberId
     * @param  bool  $includeCollections
     * @param  bool  $includeGroups
     */
    public function getMember(string $memberId, bool $includeCollections = false, bool $includeGroups = false): array
    {
        $queryParams = [];

        if ($includeCollections) {
            $queryParams['includeCollections'] = 'true';
        }

        if ($includeGroups) {
            $queryParams['includeGroups'] = 'true';
        }

        $endpoint = "/public/members/{$memberId}";
        if (! empty($queryParams)) {
            $endpoint .= '?'.http_build_query($queryParams);
        }

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Lädt ein oder mehrere Mitglieder ein.
     *
     * @param  array{emails: array<string>, type: string, accessAll: bool, collections: array, groups: array}  $data
     */
    public function inviteMembers(array $data): array
    {
        return $this->makeRequest('POST', '/public/members/invite', $data);
    }

    /**
     * Bearbeitet ein Mitglied.
     *
     * @param  string  $memberId
     * @param  array{type?: string, accessAll?: bool, collections?: array, groups?: array}  $data
     */
    public function updateMember(string $memberId, array $data): array
    {
        return $this->makeRequest('PUT', "/public/members/{$memberId}", $data);
    }

    /**
     * Löscht ein Mitglied.
     */
    public function deleteMember(string $memberId): void
    {
        $this->makeRequest('DELETE', "/public/members/{$memberId}");
    }

    // ==================== Collections-Endpunkte ====================

    /**
     * Ruft alle Collections ab.
     */
    public function getCollections(): array
    {
        $response = $this->makeRequest('GET', '/public/collections');

        // Die API gibt die Collections in einem 'data' Array zurück
        if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

    /**
     * Ruft eine einzelne Collection ab.
     *
     * @param  string  $collectionId
     */
    public function getCollection(string $collectionId): array
    {
        return $this->makeRequest('GET', "/public/collections/{$collectionId}");
    }

    /**
     * Erstellt eine neue Collection.
     *
     * @param  array{name: string, externalId?: string, groups?: array, users?: array}  $data
     */
    public function createCollection(array $data): array
    {
        return $this->makeRequest('POST', '/public/collections', $data);
    }

    /**
     * Aktualisiert eine Collection.
     *
     * @param  string  $collectionId
     * @param  array{name?: string, externalId?: string, groups?: array, users?: array}  $data
     */
    public function updateCollection(string $collectionId, array $data): array
    {
        return $this->makeRequest('PUT', "/public/collections/{$collectionId}", $data);
    }

    /**
     * Löscht eine Collection.
     *
     * @param  string  $collectionId
     */
    public function deleteCollection(string $collectionId): void
    {
        $this->makeRequest('DELETE', "/public/collections/{$collectionId}");
    }
}

