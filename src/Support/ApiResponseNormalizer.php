<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Support;

final class ApiResponseNormalizer
{
    /**
     * Unwraps Bitwarden-style `{ "data": [...] }` list responses to a plain array.
     *
     * @param  array<mixed>  $response
     * @return array<int, mixed>
     */
    public static function unwrapList(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return array_values($response['data']);
        }

        if ($response === []) {
            return [];
        }

        if (array_is_list($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Normalizes group-user responses to a list of user ID strings.
     *
     * @param  array<mixed>  $response
     * @return array<int, string>
     */
    public static function unwrapUserIds(array $response): array
    {
        $list = self::unwrapList($response);

        return array_values(array_filter(array_map(function (mixed $item): ?string {
            if (is_string($item)) {
                return $item;
            }

            if (is_array($item)) {
                $id = $item['id'] ?? $item['userId'] ?? $item['organizationUserId'] ?? null;

                return is_string($id) ? $id : null;
            }

            return null;
        }, $list)));
    }
}
