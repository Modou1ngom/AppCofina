<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetFilialeContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $user->profilCollaborateurAssocie();

            if (! $user->isSuperAdmin()) {
                $filialeId = $user->primaryFilialeId();
                if ($filialeId) {
                    session(['current_filiale_id' => $filialeId]);
                }
            } elseif (! session()->has('current_filiale_id')) {
                $profil = $user->profil;

                if ($profil && $profil->filiale_id) {
                    session(['current_filiale_id' => $profil->filiale_id]);
                }
            }
        }

        return $next($request);
    }
}
