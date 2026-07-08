<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLogistique
{
    /**
     * Accès réservé aux comptes avec le rôle logistique (user ou profil).
     * Aucun contournement admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isLogistique()) {
            abort(403, 'Accès réservé aux profils logistique.');
        }

        return $next($request);
    }
}
