<?php

declare(strict_types=1);

namespace Hwkdo\BitwardenLaravel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VaultwardenAdminSession
{
    protected ?string $cookie = null;

    public function __construct(
        protected BitwardenConfigService $configService
    ) {}

    public function getCookie(): string
    {
        if ($this->cookie !== null && $this->cookie !== '') {
            return $this->cookie;
        }

        $token = $this->configService->getAdminToken();

        if ($token === '') {
            throw new \RuntimeException('BITWARDEN_ADMIN_TOKEN / bitwardenAdminToken muss gesetzt sein!');
        }

        $url = $this->configService->getBaseUrl().'/admin';

        try {
            $response = Http::asForm()
                ->withOptions(['allow_redirects' => false])
                ->post($url, ['token' => $token]);

            $cookieHeader = $response->header('Set-Cookie');

            if (is_array($cookieHeader)) {
                $cookieHeader = $cookieHeader[0] ?? '';
            }

            if (! is_string($cookieHeader) || $cookieHeader === '') {
                // Fallback: alle Cookie-Header zusammenführen
                $cookies = $response->cookies();
                $parts = [];
                foreach ($cookies as $cookie) {
                    $parts[] = $cookie->getName().'='.$cookie->getValue();
                }
                $cookieHeader = implode('; ', $parts);
            } else {
                // Nur Name=Value aus Set-Cookie
                $cookieHeader = explode(';', $cookieHeader)[0];
            }

            if ($cookieHeader === '') {
                Log::error('Vaultwarden Admin Cookie fehlt', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException('Kein Admin-Cookie von /admin erhalten.');
            }

            $this->cookie = $cookieHeader;

            return $this->cookie;
        } catch (\Exception $e) {
            Log::error('Vaultwarden Admin Session Exception', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);

            throw $e;
        }
    }

    public function clear(): void
    {
        $this->cookie = null;
    }
}
