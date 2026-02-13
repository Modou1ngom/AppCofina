<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vérifier si l'utilisateur est authentifié
        if (Auth::check()) {
            $user = Auth::user();
            
            // Si l'utilisateur doit changer son mot de passe
            if ($user->must_change_password) {
                // Exclure la route de changement de mot de passe pour éviter une boucle infinie
                if (!$request->routeIs('password.change') && !$request->routeIs('password.change.update')) {
                    // Rediriger vers la page de changement de mot de passe
                    // même si c'est la route du challenge 2FA
                    return redirect()->route('password.change');
                }
            }
        }
        // Si l'utilisateur n'est pas encore authentifié mais qu'il y a un login.id dans la session
        // (cela signifie qu'il est en train de passer par le processus de connexion)
        elseif ($request->session()->has('login.id')) {
            $userId = $request->session()->get('login.id');
            $user = \App\Models\User::find($userId);
            
            // Si l'utilisateur doit changer son mot de passe, l'authentifier directement
            // et le rediriger vers la page de changement de mot de passe
            if ($user && $user->must_change_password) {
                Auth::login($user);
                $request->session()->forget('login.id');
                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
