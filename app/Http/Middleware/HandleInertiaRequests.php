<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $profil = null;
        $roles = [];

        if ($user) {
            $user->load('roles', 'profil');
            $profil = $user->profil;
            $roles = $user->roles->pluck('slug')->toArray();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'profil' => $profil,
                'roles' => $roles,
                'isAdmin' => $user ? $user->isAdmin() : false,
                'isMetier' => $user ? $user->isMetier() : false,
                'isControle' => $user ? $user->isControle() : false,
                'isRh' => $user ? $user->isRh() : false,
                'isExecuteurIt' => $user ? $user->isExecuteurIt() : false,
                'isResponsableDepartement' => $user ? $user->isResponsableDepartement() : false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
