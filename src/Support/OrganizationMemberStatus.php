<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Support;

/**
 * Bitwarden OrganizationUserStatusType helpers.
 *
 * 0 = Invited, 1 = Accepted, 2 = Confirmed, …
 */
final class OrganizationMemberStatus
{
    public const INVITED = 0;

    public const ACCEPTED = 1;

    public const CONFIRMED = 2;

    /**
     * @param  array<string, mixed>  $member
     */
    public static function status(array $member): int
    {
        return (int) ($member['status'] ?? -1);
    }

    /**
     * @param  array<string, mixed>  $member
     */
    public static function isConfirmed(array $member): bool
    {
        return self::status($member) === self::CONFIRMED;
    }

    /**
     * Mitglied ist noch nicht fully confirmed (Invited/Accepted/Quirk).
     *
     * @param  array<string, mixed>  $member
     */
    public static function isPending(array $member): bool
    {
        return ! self::isConfirmed($member) && self::status($member) >= 0;
    }

    /**
     * Confirm nötig bei:
     * - Status Accepted (1), oder
     * - Status Invited (0) mit gesetzter userId und hasMasterPassword (Vaultwarden-Quirk nach Registrierung).
     *
     * @param  array<string, mixed>  $member
     */
    public static function needsConfirm(array $member): bool
    {
        $status = self::status($member);

        if ($status === self::ACCEPTED) {
            return true;
        }

        if ($status !== self::INVITED) {
            return false;
        }

        $userId = trim((string) ($member['userId'] ?? ''));
        $hasMasterPassword = filter_var($member['hasMasterPassword'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $userId !== '' && $hasMasterPassword;
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $apiResponse
     * @return list<array<string, mixed>>
     */
    public static function unwrapMembers(array $apiResponse): array
    {
        $members = $apiResponse;

        if (isset($members['data']) && is_array($members['data'])) {
            $members = $members['data'];
        }

        if (isset($members['members']) && is_array($members['members'])) {
            $members = $members['members'];
        }

        if (! is_array($members)) {
            return [];
        }

        $out = [];

        foreach ($members as $member) {
            if (is_array($member)) {
                $out[] = $member;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $apiResponse
     * @param  list<string>|null  $emailFilterLower  optional: nur diese E-Mails (lowercase)
     * @return list<array<string, mixed>>
     */
    public static function membersNeedingConfirm(array $apiResponse, ?array $emailFilterLower = null): array
    {
        $filter = null;

        if ($emailFilterLower !== null) {
            $filter = [];
            foreach ($emailFilterLower as $email) {
                $normalized = strtolower(trim((string) $email));
                if ($normalized !== '') {
                    $filter[$normalized] = true;
                }
            }
        }

        $result = [];

        foreach (self::unwrapMembers($apiResponse) as $member) {
            $email = strtolower(trim((string) ($member['email'] ?? '')));
            $id = trim((string) ($member['id'] ?? ''));

            if ($email === '' || $id === '') {
                continue;
            }

            if ($filter !== null && ! isset($filter[$email])) {
                continue;
            }

            if (! self::needsConfirm($member)) {
                continue;
            }

            $result[] = $member;
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $apiResponse
     * @return list<array<string, mixed>>
     */
    public static function pendingMembers(array $apiResponse): array
    {
        $result = [];

        foreach (self::unwrapMembers($apiResponse) as $member) {
            $id = trim((string) ($member['id'] ?? ''));

            if ($id === '' || ! self::isPending($member)) {
                continue;
            }

            $result[] = $member;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $member
     */
    public static function email(array $member): string
    {
        return strtolower(trim((string) ($member['email'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $member
     */
    public static function id(array $member): string
    {
        return trim((string) ($member['id'] ?? ''));
    }
}
