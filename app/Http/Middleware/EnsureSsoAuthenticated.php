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

        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('sso.login');
    }
}
