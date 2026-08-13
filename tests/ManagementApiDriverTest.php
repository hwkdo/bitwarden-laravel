<?php

declare(strict_types=1);

use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Drivers\ManagementApiDriverProxy;
use Hwkdo\BitwardenLaravel\Drivers\NativeVaultwardenManagementApiDriver;
use Hwkdo\BitwardenLaravel\Drivers\PublicApiManagementApiDriver;
use Hwkdo\BitwardenLaravel\Enums\ManagementApiDriver;
use Hwkdo\BitwardenLaravel\Services\BitwardenConfigService;
use Hwkdo\BitwardenLaravel\Services\NativeOrgTokenService;
use Hwkdo\BitwardenLaravel\Support\ApiResponseNormalizer;
use Illuminate\Support\Facades\Http;

it('unwraps list responses with data key', function (): void {
    expect(ApiResponseNormalizer::unwrapList([
        'data' => [
            ['id' => '1'],
            ['id' => '2'],
        ],
        'object' => 'list',
    ]))->toBe([
        ['id' => '1'],
        ['id' => '2'],
    ]);
});

it('normalizes group user id lists from objects and strings', function (): void {
    expect(ApiResponseNormalizer::unwrapUserIds([
        'user-1',
        ['id' => 'user-2'],
        ['organizationUserId' => 'user-3'],
    ]))->toBe(['user-1', 'user-2', 'user-3']);
});

it('resolves public driver by default via management api interface', function (): void {
    config()->set('bitwarden-laravel.management_api_driver', 'public');

    $proxy = app(BitwardenManagementApiInterface::class);

    expect($proxy)->toBeInstanceOf(ManagementApiDriverProxy::class);

    $config = Mockery::mock(BitwardenConfigService::class);
    $config->shouldReceive('getManagementApiDriver')->andReturn(ManagementApiDriver::Public);

    $proxy = new ManagementApiDriverProxy(app(), $config);

    $driver = (new ReflectionClass($proxy))->getMethod('driver');
    $driver->setAccessible(true);

    expect($driver->invoke($proxy))->toBeInstanceOf(PublicApiManagementApiDriver::class);
});

it('resolves native driver when configured', function (): void {
    $config = Mockery::mock(BitwardenConfigService::class);
    $config->shouldReceive('getManagementApiDriver')->andReturn(ManagementApiDriver::Native);

    $proxy = new ManagementApiDriverProxy(app(), $config);

    $driver = (new ReflectionClass($proxy))->getMethod('driver');
    $driver->setAccessible(true);

    expect($driver->invoke($proxy))->toBeInstanceOf(NativeVaultwardenManagementApiDriver::class);
});

it('lists groups via native org api', function (): void {
    config()->set('bitwarden-laravel.api_url', 'https://vw.example.com/api/');
    config()->set('bitwarden-laravel.organization_id', 'org-1');

    Http::fake([
        'https://vw.example.com/api/organizations/org-1/groups' => Http::response([
            'data' => [
                ['id' => 'g1', 'name' => 'Group 1'],
            ],
        ], 200),
    ]);

    $config = Mockery::mock(BitwardenConfigService::class);
    $config->shouldReceive('getOrganizationId')->andReturn('org-1');
    $config->shouldReceive('getBaseUrl')->andReturn('https://vw.example.com');

    $token = Mockery::mock(NativeOrgTokenService::class);
    $token->shouldReceive('getToken')->andReturn('test-token');

    $driver = new NativeVaultwardenManagementApiDriver($config, $token);

    expect($driver->getGroups())->toBe([
        ['id' => 'g1', 'name' => 'Group 1'],
    ]);
});

it('updates group users via put group on native driver', function (): void {
    config()->set('bitwarden-laravel.api_url', 'https://vw.example.com/api/');

    Http::fake([
        'https://vw.example.com/api/organizations/org-1/groups/g1' => Http::sequence()
            ->push(['id' => 'g1', 'name' => 'Group 1', 'collections' => []], 200)
            ->push(['id' => 'g1', 'name' => 'Group 1', 'users' => ['u1', 'u2']], 200),
    ]);

    $config = Mockery::mock(BitwardenConfigService::class);
    $config->shouldReceive('getOrganizationId')->andReturn('org-1');
    $config->shouldReceive('getBaseUrl')->andReturn('https://vw.example.com');

    $token = Mockery::mock(NativeOrgTokenService::class);
    $token->shouldReceive('getToken')->andReturn('test-token');

    $driver = new NativeVaultwardenManagementApiDriver($config, $token);

    $result = $driver->updateGroupUsers('g1', ['u1', 'u2']);

    expect($result['users'] ?? null)->toBe(['u1', 'u2']);

    Http::assertSent(function ($request): bool {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/groups/g1')
            && $request['users'] === ['u1', 'u2']
            && $request['name'] === 'Group 1';
    });
});

it('deletes members via org users endpoint on native driver', function (): void {
    Http::fake([
        'https://vw.example.com/api/organizations/org-1/users/member-1' => Http::response([], 200),
    ]);

    $config = Mockery::mock(BitwardenConfigService::class);
    $config->shouldReceive('getOrganizationId')->andReturn('org-1');
    $config->shouldReceive('getBaseUrl')->andReturn('https://vw.example.com');

    $token = Mockery::mock(NativeOrgTokenService::class);
    $token->shouldReceive('getToken')->andReturn('test-token');

    $driver = new NativeVaultwardenManagementApiDriver($config, $token);
    $driver->deleteMember('member-1');

    Http::assertSent(function ($request): bool {
        return $request->method() === 'DELETE'
            && $request->url() === 'https://vw.example.com/api/organizations/org-1/users/member-1';
    });
});

it('invites members via native org invite endpoint', function (): void {
    Http::fake([
        'https://vw.example.com/api/organizations/org-1/users/invite' => Http::response(['ok' => true], 200),
    ]);

    $config = Mockery::mock(BitwardenConfigService::class);
    $config->shouldReceive('getOrganizationId')->andReturn('org-1');
    $config->shouldReceive('getBaseUrl')->andReturn('https://vw.example.com');

    $token = Mockery::mock(NativeOrgTokenService::class);
    $token->shouldReceive('getToken')->andReturn('test-token');

    $driver = new NativeVaultwardenManagementApiDriver($config, $token);

    $result = $driver->inviteMembers([
        'emails' => ['a@example.com'],
        'type' => '2',
        'accessAll' => false,
    ]);

    expect($result)->toBe(['ok' => true]);

    Http::assertSent(function ($request): bool {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/users/invite')
            && $request['emails'] === ['a@example.com']
            && $request['type'] === 2
            && ! array_key_exists('permissions', $request->data());
    });
});
