<?php

namespace App\Services;

use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Integrations\Sso\SsoUser;
use Illuminate\Http\Request;

class CurrentUserService
{
    public const SESSION_KEY = 'sso_user';

    public const ROLE_SESSION_KEY = 'taskhub_roles';

    private ?SsoUser $resolvedUser = null;

    public function __construct(
        private readonly Request $request,
        private readonly SsoClient $ssoClient,
    ) {}

    public function user(): SsoUser
    {
        if ($this->resolvedUser instanceof SsoUser) {
            return $this->resolvedUser;
        }

        $sessionUser = $this->request->session()->get(self::SESSION_KEY);

        if (is_array($sessionUser)) {
            return $this->resolvedUser = SsoUser::fromPayload($sessionUser);
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

    public function roles(): array
    {
        $roles = $this->request->session()->get(self::ROLE_SESSION_KEY, []);

        return is_array($roles) ? array_values(array_filter($roles, 'is_string')) : [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }
}
