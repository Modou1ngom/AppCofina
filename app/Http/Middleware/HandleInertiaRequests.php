<?php

namespace App\Http\Middleware;

use App\Models\SigStaff;
use App\Support\NotificationPresenter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
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

        $sigStaffFiche = ['exists' => false, 'id' => null];
        $notificationsPreview = [];
        $notificationsUnreadCount = 0;
        if ($user) {
            $user->load('roles');
            $user->profilCollaborateurAssocie();
            $profil = $user->profil;
            $roles = $user->roles->pluck('slug')->toArray();
            if ($profil) {
                $sig = SigStaff::query()->where('profile_id', $profil->id)->first();
                $sigStaffFiche = [
                    'exists' => $sig !== null,
                    'id' => $sig?->id,
                ];
            }

            $notificationsUnreadCount = $user->unreadNotifications()->count();
            $notificationsPreview = $user->notifications()
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(fn (DatabaseNotification $n) => NotificationPresenter::toWebArray($n))
                ->values()
                ->all();
        }

        return [
            ...parent::share($request),
            /** Jeton CSRF à jour pour les requêtes fetch hors Inertia (le meta du layout initial peut être périmé). */
            'csrf_token' => csrf_token(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'profil' => $profil,
                'roles' => $roles,
                'isAdmin' => $user ? $user->isAdmin() : false,
                'isSuperAdmin' => $user ? $user->isSuperAdmin() : false,
                'isMetier' => $user ? $user->isMetier() : false,
                'isControle' => $user ? $user->isControle() : false,
                'isRh' => $user ? $user->isRh() : false,
                'isFinance' => $user ? $user->isFinance() : false,
                'isMd' => $user ? $user->isMd() : false,
                'isConformite' => $user ? $user->isConformite() : false,
                'sigStaffFiche' => $sigStaffFiche,
                'isExecuteurIt' => $user ? $user->isExecuteurIt() : false,
                'isResponsableDepartement' => $user ? $user->isResponsableDepartement() : false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notifications' => [
                'unread_count' => $notificationsUnreadCount,
                'preview' => $notificationsPreview,
            ],
        ];
    }
}
