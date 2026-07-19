<?php

namespace App\Integrations\Sso;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SsoClient
{
    public function fetchCurrentUser(string $accessToken): SsoUser
    {
        $baseUrl = config('sso.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new SsoException('SSO base URL is not configured.');
        }

        $userInfoPath = config('sso.userinfo_path') ?: config('sso.validate_path');

        if (! is_string($userInfoPath) || $userInfoPath === '') {
            throw new SsoException('SSO user info path is not configured.');
        }

        try {
            // TODO: Align request method, path, headers, and payload shape with the company SSO user info contract.
            $response = Http::baseUrl($baseUrl)
                ->timeout((int) config('sso.timeout', 5))
                ->acceptJson()
                ->withToken($accessToken)
                ->get($userInfoPath);
        } catch (ConnectionException $exception) {
            throw new SsoException('Unable to connect to SSO user info service.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new SsoException(sprintf('SSO user info request failed with HTTP status %d.', $response->status()));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SsoException('SSO user info response is not a JSON object.');
        }

        return SsoUser::fromPayload($payload);
    }

    public function validateToken(string $token): SsoUser
    {
        return $this->fetchCurrentUser($token);
    }
}
