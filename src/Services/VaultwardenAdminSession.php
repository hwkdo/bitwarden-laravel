<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VaultwardenAdminSession
{
    protected const CACHE_KEY = 'bitwarden-laravel.vaultwarden_admin_cookie';

    /**
     * Vaultwarden Admin-JWT-Lebensdauer (Minuten). Etwas kürzer cachen, damit wir vor Ablauf neu loggen.
     */
    protected const CACHE_TTL_SECONDS = 15 * 60;

    protected ?string $cookie = null;

    public function __construct(
        protected BitwardenConfigService $configService
    ) {}

    public function getCookie(): string
    {
        if ($this->cookie !== null && $this->cookie !== '') {
            return $this->cookie;
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            $this->cookie = $cached;

            return $this->cookie;
        }

        return $this->loginAndStoreCookie();
    }

    public function clear(): void
    {
        $this->cookie = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Erzwingt einen neuen Admin-Login (z. B. nach 401 oder leerem Cookie).
     */
    public function refresh(): string
    {
        $this->clear();

        return $this->loginAndStoreCookie();
    }

    protected function loginAndStoreCookie(): string
    {
        $token = $this->configService->getAdminToken();

        if ($token === '') {
            throw new \RuntimeException('BITWARDEN_ADMIN_TOKEN / bitwardenAdminToken muss gesetzt sein!');
        }

        $url = $this->configService->getBaseUrl().'/admin';

        try {
            $response = Http::asForm()
                ->withOptions(['allow_redirects' => false])
                ->timeout(30)
                ->post($url, ['token' => $token]);

            $cookieHeader = $this->extractCookie($response);

            if ($cookieHeader === '') {
                $body = $response->body();
                $status = $response->status();

                Log::error('Vaultwarden Admin Cookie fehlt', [
                    'status' => $status,
                    'body' => $body,
                ]);

                if ($status === 429 || str_contains(strtolower($body), 'too many requests')) {
                    throw new \RuntimeException(
                        'Vaultwarden Admin-Login rate-limitiert. Bitte kurz warten und erneut versuchen.'
                    );
                }

                throw new \RuntimeException(
                    "Kein Admin-Cookie von /admin erhalten (HTTP {$status})."
                );
            }

            $this->cookie = $cookieHeader;
            Cache::put(self::CACHE_KEY, $cookieHeader, self::CACHE_TTL_SECONDS);

            return $this->cookie;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Vaultwarden Admin Session Exception', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);

            throw $e;
        }
    }

    protected function extractCookie(\Illuminate\Http\Client\Response $response): string
    {
        $cookieHeader = $response->header('Set-Cookie');

        if (is_array($cookieHeader)) {
            foreach ($cookieHeader as $header) {
                if (! is_string($header)) {
                    continue;
                }

                $nameValue = explode(';', $header)[0];

                if (str_starts_with($nameValue, 'VW_ADMIN=')) {
                    return $nameValue;
                }
            }

            $cookieHeader = $cookieHeader[0] ?? '';
        }

        if (is_string($cookieHeader) && $cookieHeader !== '') {
            $nameValue = explode(';', $cookieHeader)[0];

            if (str_starts_with($nameValue, 'VW_ADMIN=') || str_contains($nameValue, '=')) {
                return $nameValue;
            }
        }

        $parts = [];
        foreach ($response->cookies() as $cookie) {
            if ($cookie->getName() === 'VW_ADMIN' || $cookie->getName() !== '') {
                $parts[] = $cookie->getName().'='.$cookie->getValue();
            }
        }

        foreach ($parts as $part) {
            if (str_starts_with($part, 'VW_ADMIN=')) {
                return $part;
            }
        }

        return $parts[0] ?? '';
    }
}
