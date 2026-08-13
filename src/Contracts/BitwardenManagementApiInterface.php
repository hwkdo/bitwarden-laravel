<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Contracts;

interface BitwardenManagementApiInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGroups(): array;

    /**
     * @return array<string, mixed>
     */
    public function getGroup(string $groupId): array;

    /**
     * @param  array{name: string, accessAll?: bool, collections?: array, users?: array}  $data
     * @return array<string, mixed>
     */
    public function createGroup(array $data): array;

    /**
     * @param  array{name?: string, accessAll?: bool, collections?: array, users?: array}  $data
     * @return array<string, mixed>
     */
    public function updateGroup(string $groupId, array $data): array;

    public function deleteGroup(string $groupId): void;

    /**
     * @return array<int, string>
     */
    public function getGroupUsers(string $groupId): array;

    /**
     * @param  array<int, string>  $userIds
     * @return array<string, mixed>
     */
    public function updateGroupUsers(string $groupId, array $userIds): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMembers(bool $includeCollections = false, bool $includeGroups = false): array;

    /**
     * @return array<string, mixed>
     */
    public function getMember(string $memberId, bool $includeCollections = false, bool $includeGroups = false): array;

    /**
     * @param  array{emails: array<int, string>, type?: string|int, accessAll?: bool, collections?: array, groups?: array}  $data
     * @return array<string, mixed>
     */
    public function inviteMembers(array $data): array;

    /**
     * @param  array{type?: string|int, accessAll?: bool, collections?: array, groups?: array}  $data
     * @return array<string, mixed>
     */
    public function updateMember(string $memberId, array $data): array;

    public function deleteMember(string $memberId): void;
}
