<?php

namespace App\Services;

use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Integrations\Sso\SsoUser;
use Illuminate\Http\Request;

class CurrentUserService
{
    private ?SsoUser $resolvedUser = null;

    public function __construct(
        private readonly Request $request,
        private readonly SsoClient $ssoClient,
    ) {
    }

    public function user(): SsoUser
    {
        if ($this->resolvedUser instanceof SsoUser) {
            return $this->resolvedUser;
        }

        $token = $this->request->bearerToken();

        if (! is_string($token) || $token === '') {
            throw new SsoException('Missing SSO bearer token.');
        }

        return $this->resolvedUser = $this->ssoClient->validateToken($token);
    }

    public function employeeNo(): string
    {
        return $this->user()->employeeNo();
    }
}
