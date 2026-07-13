<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSsoAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('sso_user')) {
            return $next($request);
        }

        // TODO: Redirect to the company SSO login URL after headquarters confirms:
        // login URL, callback path, state parameter, token/code parameter name,
        // token validation endpoint, refresh behavior, and logout URL.
        abort(401, 'SSO protocol is not configured.');
    }
}
