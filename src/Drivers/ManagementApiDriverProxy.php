<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Drivers;

use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Enums\ManagementApiDriver;
use Hwkdo\BitwardenLaravel\Services\BitwardenConfigService;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the concrete management API driver on each call so Admin-UI switches apply without restart.
 */
class ManagementApiDriverProxy implements BitwardenManagementApiInterface
{
    public function __construct(
        protected Container $container,
        protected BitwardenConfigService $configService
    ) {}

    protected function driver(): BitwardenManagementApiInterface
    {
        $driver = $this->configService->getManagementApiDriver();

        return match ($driver) {
            ManagementApiDriver::Native => $this->container->make(NativeVaultwardenManagementApiDriver::class),
            ManagementApiDriver::Public => $this->container->make(PublicApiManagementApiDriver::class),
        };
    }

    public function getGroups(): array
    {
        return $this->driver()->getGroups();
    }

    public function getGroup(string $groupId): array
    {
        return $this->driver()->getGroup($groupId);
    }

    public function createGroup(array $data): array
    {
        return $this->driver()->createGroup($data);
    }

    public function updateGroup(string $groupId, array $data): array
    {
        return $this->driver()->updateGroup($groupId, $data);
    }

    public function deleteGroup(string $groupId): void
    {
        $this->driver()->deleteGroup($groupId);
    }

    public function getGroupUsers(string $groupId): array
    {
        return $this->driver()->getGroupUsers($groupId);
    }

    public function updateGroupUsers(string $groupId, array $userIds): array
    {
        return $this->driver()->updateGroupUsers($groupId, $userIds);
    }

    public function getMembers(bool $includeCollections = false, bool $includeGroups = false): array
    {
        return $this->driver()->getMembers($includeCollections, $includeGroups);
    }

    public function getMember(string $memberId, bool $includeCollections = false, bool $includeGroups = false): array
    {
        return $this->driver()->getMember($memberId, $includeCollections, $includeGroups);
    }

    public function inviteMembers(array $data): array
    {
        return $this->driver()->inviteMembers($data);
    }

    public function updateMember(string $memberId, array $data): array
    {
        return $this->driver()->updateMember($memberId, $data);
    }

    public function deleteMember(string $memberId): void
    {
        $this->driver()->deleteMember($memberId);
    }
}
