<?php

namespace App\Http\Middleware;

use App\Support\Agenti\AgentiDevBypass;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invia i guest verso il login dev quando il bypass è attivo (solo wizard simulazione).
 */
class RedirectGuestToAgentiDevLogin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! AgentiDevBypass::isEnabled() || auth()->check()) {
            return $next($request);
        }

        if ($request->routeIs('agenti.dev.login', 'filament.agenti.auth.*')) {
            return $next($request);
        }

        if ($request->routeIs('filament.agenti.pages.simulazione-finanziamento')) {
            return redirect()->route('agenti.dev.login');
        }

        return $next($request);
    }
}
