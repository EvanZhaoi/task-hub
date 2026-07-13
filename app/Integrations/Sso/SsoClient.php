<?php

namespace App\Integrations\Sso;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SsoClient
{
    public function validateToken(string $token): SsoUser
    {
        $baseUrl = config('sso.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new SsoException('SSO base URL is not configured.');
        }

        try {
            // TODO: Align request path, authentication headers, and payload shape with the company SSO contract.
            $response = Http::baseUrl($baseUrl)
                ->timeout((int) config('sso.timeout', 5))
                ->acceptJson()
                ->withToken($token)
                ->post((string) config('sso.validate_path', '/api/sso/validate'));
        } catch (ConnectionException $exception) {
            throw new SsoException('Unable to connect to SSO service.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new SsoException(sprintf('SSO rejected token with HTTP status %d.', $response->status()));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SsoException('SSO response is not a JSON object.');
        }

        return SsoUser::fromPayload($payload);
    }
}
