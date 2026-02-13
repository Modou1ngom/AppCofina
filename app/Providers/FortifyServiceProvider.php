<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Events\Login;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthentication();
        $this->configureTwoFactorRedirect();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('error'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * Configure authentication to prevent inactive users from logging in.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            // Vérifier d'abord si l'utilisateur existe (actif ou non)
            $userExists = User::where('email', $request->email)->exists();
            
            if ($userExists) {
                // Vérifier si l'utilisateur est actif
                $user = User::where('email', $request->email)
                    ->where('is_active', true)
                    ->first();

                // Si l'utilisateur existe mais n'est pas actif
                if (!$user) {
                    $request->session()->flash('error', 'Votre compte utilisateur a été désactivé. Vous ne pouvez pas vous connecter. Veuillez contacter votre administrateur système pour réactiver votre compte.');
                    return null;
                }

                // Vérifier le mot de passe
                if (Auth::validate([
                    'email' => $request->email,
                    'password' => $request->password,
                ])) {
                    return $user;
                }
            }

            return null;
        });
    }

    /**
     * Configure le comportement du 2FA pour les utilisateurs qui doivent changer leur mot de passe.
     */
    private function configureTwoFactorRedirect(): void
    {
        // Écouter l'événement de connexion pour contourner le 2FA si nécessaire
        Event::listen(Login::class, function (Login $event) {
            $user = $event->user;
            
            // Si l'utilisateur doit changer son mot de passe, supprimer le login.id
            // pour éviter que Fortify ne redirige vers le challenge 2FA
            if ($user->must_change_password) {
                request()->session()->forget('login.id');
            }
        });

        // Personnaliser la vue du challenge 2FA pour rediriger si l'utilisateur doit changer son mot de passe
        Fortify::twoFactorChallengeView(function (Request $request) {
            // Vérifier si l'utilisateur est en cours de connexion et doit changer son mot de passe
            if ($request->session()->has('login.id')) {
                $userId = $request->session()->get('login.id');
                $user = User::find($userId);
                
                if ($user && $user->must_change_password) {
                    // Authentifier l'utilisateur directement et le rediriger
                    Auth::login($user);
                    $request->session()->forget('login.id');
                    return redirect()->route('password.change');
                }
            }
            
            // Si l'utilisateur est déjà authentifié et doit changer son mot de passe
            if (Auth::check() && Auth::user()->must_change_password) {
                return redirect()->route('password.change');
            }
            
            // Sinon, afficher la vue normale du challenge 2FA
            return Inertia::render('auth/TwoFactorChallenge');
        });
    }
}
