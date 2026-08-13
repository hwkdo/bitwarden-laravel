<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Drivers;

use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenConfigService;
use Hwkdo\BitwardenLaravel\Services\BitwardenTokenService;
use Hwkdo\BitwardenLaravel\Support\ApiResponseNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PublicApiManagementApiDriver implements BitwardenManagementApiInterface
{
    public function __construct(
        protected BitwardenConfigService $configService,
        protected BitwardenTokenService $tokenService
    ) {}

    protected function getApiBaseUrl(): string
    {
        $apiUrl = $this->configService->getApiUrl();

        if (! str_ends_with($apiUrl, '/api/')) {
            $apiUrl = rtrim($apiUrl, '/').'/api/';
        }

        return $apiUrl;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->tokenService->getToken();
        $url = $this->getApiBaseUrl().ltrim($endpoint, '/');

        try {
            $request = Http::withToken($token);

            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
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

            if ($jsonResponse === null) {
                $jsonResponse = [];
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

    public function getGroups(): array
    {
        return ApiResponseNormalizer::unwrapList($this->makeRequest('GET', '/public/groups'));
    }

    public function getGroup(string $groupId): array
    {
        return $this->makeRequest('GET', "/public/groups/{$groupId}");
    }

    public function createGroup(array $data): array
    {
        return $this->makeRequest('POST', '/public/groups', $data);
    }

    public function updateGroup(string $groupId, array $data): array
    {
        return $this->makeRequest('PUT', "/public/groups/{$groupId}", $data);
    }

    public function deleteGroup(string $groupId): void
    {
        $this->makeRequest('DELETE', "/public/groups/{$groupId}");
    }

    public function getGroupUsers(string $groupId): array
    {
        return ApiResponseNormalizer::unwrapUserIds(
            $this->makeRequest('GET', "/public/groups/{$groupId}/users")
        );
    }

    public function updateGroupUsers(string $groupId, array $userIds): array
    {
        return $this->makeRequest('PUT', "/public/groups/{$groupId}/users", $userIds);
    }

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
        if ($queryParams !== []) {
            $endpoint .= '?'.http_build_query($queryParams);
        }

        return ApiResponseNormalizer::unwrapList($this->makeRequest('GET', $endpoint));
    }

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
        if ($queryParams !== []) {
            $endpoint .= '?'.http_build_query($queryParams);
        }

        return $this->makeRequest('GET', $endpoint);
    }

    public function inviteMembers(array $data): array
    {
        return $this->makeRequest('POST', '/public/members/invite', $data);
    }

    public function updateMember(string $memberId, array $data): array
    {
        return $this->makeRequest('PUT', "/public/members/{$memberId}", $data);
    }

    public function deleteMember(string $memberId): void
    {
        $this->makeRequest('DELETE', "/public/members/{$memberId}");
    }
}
