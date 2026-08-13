<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Drivers;

use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\BitwardenConfigService;
use Hwkdo\BitwardenLaravel\Services\NativeOrgTokenService;
use Hwkdo\BitwardenLaravel\Support\ApiResponseNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NativeVaultwardenManagementApiDriver implements BitwardenManagementApiInterface
{
    public function __construct(
        protected BitwardenConfigService $configService,
        protected NativeOrgTokenService $tokenService,
    ) {}

    protected function getOrganizationId(): string
    {
        $orgId = $this->configService->getOrganizationId();

        if ($orgId === '') {
            throw new \RuntimeException('BITWARDEN_ORGANIZATION_ID / bitwardenOrganizationId muss gesetzt sein!');
        }

        return $orgId;
    }

    protected function orgApiPath(string $suffix): string
    {
        $orgId = $this->getOrganizationId();

        return '/api/organizations/'.$orgId.'/'.ltrim($suffix, '/');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    protected function makeOrgRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->tokenService->getToken();
        $url = $this->configService->getBaseUrl().'/'.ltrim($endpoint, '/');

        try {
            $request = Http::withToken($token)->acceptJson();

            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                $request = $request->asJson();
            }

            $response = $request->{strtolower($method)}($url, $data);

            if (! $response->successful()) {
                Log::error('Bitwarden Native Org API Request Failed', [
                    'method' => $method,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException(
                    "Fehler bei der Native Org API-Anfrage: {$response->status()} - {$response->body()}"
                );
            }

            $jsonResponse = $response->json();

            return $jsonResponse === null ? [] : $jsonResponse;
        } catch (\Exception $e) {
            Log::error('Bitwarden Native Org API Request Exception', [
                'method' => $method,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getGroups(): array
    {
        return ApiResponseNormalizer::unwrapList(
            $this->makeOrgRequest('GET', $this->orgApiPath('groups'))
        );
    }

    public function getGroup(string $groupId): array
    {
        return $this->makeOrgRequest('GET', $this->orgApiPath("groups/{$groupId}"));
    }

    public function createGroup(array $data): array
    {
        return $this->makeOrgRequest('POST', $this->orgApiPath('groups'), $data);
    }

    public function updateGroup(string $groupId, array $data): array
    {
        return $this->makeOrgRequest('PUT', $this->orgApiPath("groups/{$groupId}"), $data);
    }

    public function deleteGroup(string $groupId): void
    {
        $this->makeOrgRequest('DELETE', $this->orgApiPath("groups/{$groupId}"));
    }

    public function getGroupUsers(string $groupId): array
    {
        return ApiResponseNormalizer::unwrapUserIds(
            $this->makeOrgRequest('GET', $this->orgApiPath("groups/{$groupId}/users"))
        );
    }

    public function updateGroupUsers(string $groupId, array $userIds): array
    {
        $group = $this->getGroup($groupId);

        return $this->updateGroup($groupId, [
            'name' => $group['name'] ?? '',
            'collections' => $group['collections'] ?? [],
            'users' => array_values($userIds),
        ]);
    }

    public function getMembers(bool $includeCollections = false, bool $includeGroups = false): array
    {
        return ApiResponseNormalizer::unwrapList(
            $this->makeOrgRequest('GET', $this->orgApiPath('users'))
        );
    }

    public function getMember(string $memberId, bool $includeCollections = false, bool $includeGroups = false): array
    {
        return $this->makeOrgRequest('GET', $this->orgApiPath("users/{$memberId}"));
    }

    public function inviteMembers(array $data): array
    {
        // Vaultwarden liefert 422, wenn permissions:null mitgeschickt wird.
        $payload = [
            'emails' => $data['emails'] ?? [],
            'type' => isset($data['type']) ? (int) $data['type'] : 2,
            'accessAll' => (bool) ($data['accessAll'] ?? false),
            'collections' => $data['collections'] ?? [],
            'groups' => $data['groups'] ?? [],
        ];

        if (array_key_exists('accessSecretsManager', $data)) {
            $payload['accessSecretsManager'] = (bool) $data['accessSecretsManager'];
        }

        if (array_key_exists('permissions', $data) && $data['permissions'] !== null) {
            $payload['permissions'] = $data['permissions'];
        }

        return $this->makeOrgRequest('POST', $this->orgApiPath('users/invite'), $payload);
    }

    public function updateMember(string $memberId, array $data): array
    {
        $payload = $data;

        if (isset($payload['type'])) {
            $payload['type'] = (int) $payload['type'];
        }

        return $this->makeOrgRequest('PUT', $this->orgApiPath("users/{$memberId}"), $payload);
    }

    public function deleteMember(string $memberId): void
    {
        // Entfernt nur die Organisations-Mitgliedschaft (wie Public API DELETE /public/members/{id}),
        // nicht das Vaultwarden-Benutzerkonto. $memberId = Organization-User-ID.
        $this->makeOrgRequest('DELETE', $this->orgApiPath("users/{$memberId}"));
    }
}
