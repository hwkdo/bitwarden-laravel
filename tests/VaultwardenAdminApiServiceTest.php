<?php

declare(strict_types=1);

use Hwkdo\BitwardenLaravel\Services\BitwardenConfigService;
use Hwkdo\BitwardenLaravel\Services\VaultwardenAdminApiService;
use Hwkdo\BitwardenLaravel\Services\VaultwardenAdminSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
});

it('deletes a vaultwarden user account via admin api', function (): void {
    config()->set('bitwarden-laravel.api_url', 'https://vw.example.com/api/');
    config()->set('bitwarden-laravel.admin_token', 'admin-secret');

    Http::fake([
        'https://vw.example.com/admin' => Http::response('', 200, [
            'Set-Cookie' => 'VW_ADMIN=session-cookie; Path=/admin; HttpOnly',
        ]),
        'https://vw.example.com/admin/users/user-abc/delete' => Http::response([], 200),
    ]);

    $service = app(VaultwardenAdminApiService::class);
    $service->deleteUserAccount('user-abc');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://vw.example.com/admin'
            && $request->method() === 'POST';
    });

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://vw.example.com/admin/users/user-abc/delete'
            && $request->method() === 'POST'
            && $request->hasHeader('Cookie', 'VW_ADMIN=session-cookie')
            && $request->hasHeader('Origin', 'https://vw.example.com');
    });
});

it('reuses cached admin cookie across account deletes without re-login', function (): void {
    config()->set('bitwarden-laravel.api_url', 'https://vw.example.com/api/');
    config()->set('bitwarden-laravel.admin_token', 'admin-secret');

    Http::fake([
        'https://vw.example.com/admin' => Http::response('', 200, [
            'Set-Cookie' => 'VW_ADMIN=cached-cookie; Path=/admin; HttpOnly',
        ]),
        'https://vw.example.com/admin/users/*' => Http::response([], 200),
    ]);

    $service = app(VaultwardenAdminApiService::class);
    $service->deleteUserAccount('user-1');
    $service->deleteUserAccount('user-2');
    $service->deleteUserAccount('user-3');

    Http::assertSentCount(4); // 1x login + 3x delete

    $loginCount = 0;
    Http::assertSent(function ($request) use (&$loginCount): bool {
        if ($request->url() === 'https://vw.example.com/admin' && $request->method() === 'POST') {
            $loginCount++;
        }

        return true;
    });

    expect($loginCount)->toBe(1);
});

it('rejects empty user id for account delete', function (): void {
    $config = Mockery::mock(BitwardenConfigService::class);
    $session = Mockery::mock(VaultwardenAdminSession::class);

    $service = new VaultwardenAdminApiService($config, $session);

    $service->deleteUserAccount('   ');
})->throws(InvalidArgumentException::class);

it('throws when admin delete request fails', function (): void {
    config()->set('bitwarden-laravel.api_url', 'https://vw.example.com/api/');
    config()->set('bitwarden-laravel.admin_token', 'admin-secret');

    Http::fake([
        'https://vw.example.com/admin' => Http::response('', 200, [
            'Set-Cookie' => 'VW_ADMIN=session-cookie; Path=/admin',
        ]),
        'https://vw.example.com/admin/users/missing/delete' => Http::response('not found', 404),
    ]);

    app(VaultwardenAdminApiService::class)->deleteUserAccount('missing');
})->throws(RuntimeException::class);

it('surfaces rate limit errors from admin login', function (): void {
    config()->set('bitwarden-laravel.api_url', 'https://vw.example.com/api/');
    config()->set('bitwarden-laravel.admin_token', 'admin-secret');

    Http::fake([
        'https://vw.example.com/admin' => Http::response('Too many requests, try again later.', 429),
    ]);

    app(VaultwardenAdminSession::class)->getCookie();
})->throws(RuntimeException::class, 'rate-limitiert');
