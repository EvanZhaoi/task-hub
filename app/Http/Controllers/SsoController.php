<?php

namespace App\Http\Controllers;

use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Services\CurrentUserService;
use App\Services\TaskhubRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SsoController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $loginUrl = config('sso.login_url');
        $clientId = config('sso.client_id');

        if (! is_string($loginUrl) || $loginUrl === '') {
            abort(503, 'SSO login URL is not configured.');
        }

        if (! is_string($clientId) || $clientId === '') {
            abort(503, 'SSO client ID is not configured.');
        }

        $request->session()->put('url.intended', $request->session()->get('url.intended', route('tasks.index')));

        $query = http_build_query(array_filter([
            'response_type' => 'token',
            'client_id' => $clientId,
            'redirect_uri' => route('sso.callback'),
            'scope' => config('sso.scope'),
        ], fn (mixed $value): bool => is_string($value) && $value !== ''));

        return redirect()->away($loginUrl.(str_contains($loginUrl, '?') ? '&' : '?').$query);
    }

    public function callback(): Response
    {
        return Inertia::render('Sso/Callback');
    }

    public function store(Request $request, SsoClient $ssoClient, TaskhubRoleService $roleService): JsonResponse
    {
        $validated = $request->validate([
            'accessToken' => ['required', 'string'],
        ]);

        try {
            $user = $ssoClient->fetchCurrentUser($validated['accessToken']);
        } catch (SsoException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        $roles = $roleService->rolesFor($user);

        $request->session()->put(CurrentUserService::SESSION_KEY, $user->toSessionPayload());
        $request->session()->put(CurrentUserService::ROLE_SESSION_KEY, $roles);
        $request->session()->regenerate();

        return response()->json([
            'redirectTo' => $request->session()->pull('url.intended', route('tasks.index')),
            'roles' => $roles,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            CurrentUserService::SESSION_KEY,
            CurrentUserService::ROLE_SESSION_KEY,
        ]);

        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
