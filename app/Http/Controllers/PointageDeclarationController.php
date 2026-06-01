<?php

namespace App\Http\Controllers;

use App\Models\PointageDeclaration;
use App\Models\User;
use App\Services\PointageNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PointageDeclarationController extends Controller
{
    public function index(Request $request): Response
    {
        $declarations = PointageDeclaration::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PointageDeclaration $d) => $this->serializeDeclaration($d));

        return Inertia::render('pointage/declarations/Index', [
            'declarations' => $declarations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('pointage/declarations/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date_concernee' => ['required', 'date'],
            'motif' => ['required', 'string', 'max:5000'],
        ]);

        $declaration = PointageDeclaration::create([
            'user_id' => $request->user()->id,
            'date_concernee' => $validated['date_concernee'],
            'motif' => $validated['motif'],
            'statut' => PointageDeclaration::STATUT_PENDING_MANAGER,
        ]);

        PointageNotificationService::notifyDeclarationSubmitted($declaration);

        return redirect()->route('pointage.declarations.index')
            ->with('success', 'Déclaration enregistrée. Elle sera examinée par votre manager.');
    }

    public function validationManager(Request $request): Response
    {
        $user = $request->user();
        $query = PointageDeclaration::query()
            ->where('statut', PointageDeclaration::STATUT_PENDING_MANAGER)
            ->with(['user.profil']);

        if (! $user->isAdmin() && ! $user->isRh()) {
            $profil = $user->profil;
            if ($profil === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('user.profil', function ($q) use ($profil): void {
                    $q->where('n_plus_1_id', $profil->id);
                });
            }
        }

        $declarations = $query->orderByDesc('created_at')
            ->get()
            ->map(fn (PointageDeclaration $d) => $this->serializeDeclaration($d, true));

        return Inertia::render('pointage/declarations/ValidationManager', [
            'declarations' => $declarations,
        ]);
    }

    public function decisionManager(Request $request, PointageDeclaration $declaration): RedirectResponse
    {
        if ($declaration->statut !== PointageDeclaration::STATUT_PENDING_MANAGER) {
            return back()->with('error', 'Cette déclaration n’est plus en attente manager.');
        }

        if (! $this->canDecideAsManager($request->user(), $declaration)) {
            abort(403, 'Vous n’êtes pas habilité à traiter cette déclaration.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['decision'] === 'approve') {
            $declaration->update([
                'statut' => PointageDeclaration::STATUT_PENDING_RH,
                'manager_user_id' => $request->user()->id,
                'decided_at_manager' => now(),
                'commentaire_manager' => $validated['commentaire'] ?? null,
            ]);
            $message = 'Déclaration transmise aux RH.';
        } else {
            $declaration->update([
                'statut' => PointageDeclaration::STATUT_REJECTED_BY_MANAGER,
                'manager_user_id' => $request->user()->id,
                'decided_at_manager' => now(),
                'commentaire_manager' => $validated['commentaire'] ?? null,
            ]);
            $message = 'Déclaration rejetée.';
        }

        $declaration->refresh();
        PointageNotificationService::notifyManagerDecision(
            $declaration,
            $validated['decision'] === 'approve'
        );

        return back()->with('success', $message);
    }

    public function validationRh(Request $request): Response
    {
        $declarations = PointageDeclaration::query()
            ->where('statut', PointageDeclaration::STATUT_PENDING_RH)
            ->with(['user'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PointageDeclaration $d) => $this->serializeDeclaration($d, true));

        return Inertia::render('pointage/declarations/ValidationRh', [
            'declarations' => $declarations,
        ]);
    }

    public function decisionRh(Request $request, PointageDeclaration $declaration): RedirectResponse
    {
        if ($declaration->statut !== PointageDeclaration::STATUT_PENDING_RH) {
            return back()->with('error', 'Cette déclaration n’est plus en attente RH.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['decision'] === 'approve') {
            $declaration->update([
                'statut' => PointageDeclaration::STATUT_APPROVED,
                'rh_user_id' => $request->user()->id,
                'decided_at_rh' => now(),
                'commentaire_rh' => $validated['commentaire'] ?? null,
            ]);
            $message = 'Déclaration approuvée.';
        } else {
            $declaration->update([
                'statut' => PointageDeclaration::STATUT_REJECTED_BY_RH,
                'rh_user_id' => $request->user()->id,
                'decided_at_rh' => now(),
                'commentaire_rh' => $validated['commentaire'] ?? null,
            ]);
            $message = 'Déclaration rejetée.';
        }

        $declaration->refresh();
        PointageNotificationService::notifyRhDecision(
            $declaration,
            $validated['decision'] === 'approve'
        );

        return back()->with('success', $message);
    }

    private function canDecideAsManager(User $user, PointageDeclaration $declaration): bool
    {
        if ($user->isAdmin() || $user->isRh()) {
            return true;
        }

        $profil = $user->profil;
        $declarerProfil = $declaration->user?->profil;

        return $profil !== null
            && $declarerProfil !== null
            && (int) $declarerProfil->n_plus_1_id === (int) $profil->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDeclaration(PointageDeclaration $d, bool $includeUser = false): array
    {
        $base = [
            'id' => $d->id,
            'date_concernee' => $d->date_concernee->format('Y-m-d'),
            'motif' => $d->motif,
            'statut' => $d->statut,
            'created_at' => $d->created_at?->toIso8601String(),
            'commentaire_manager' => $d->commentaire_manager,
            'commentaire_rh' => $d->commentaire_rh,
        ];

        if ($includeUser && $d->relationLoaded('user') && $d->user) {
            $base['user'] = [
                'name' => $d->user->name,
                'email' => $d->user->email,
            ];
        }

        return $base;
    }
}
