<?php

namespace App\Http\Controllers;

use App\Models\PointageSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PointageSiteController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = (int) $request->get('per_page', 15);
        $sites = PointageSite::query()
            ->orderBy('nom')
            ->paginate($perPage);

        return Inertia::render('pointage/sites/Index', [
            'sites' => $sites,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('pointage/sites/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        PointageSite::create([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] ?? true,
            'code_public' => Str::lower(Str::random(14)),
            'secret_token' => Str::random(40),
        ]);

        return redirect()->route('pointage.sites.index')
            ->with('success', 'Site de pointage créé.');
    }

    public function show(PointageSite $site): Response
    {
        return Inertia::render('pointage/sites/Show', [
            'site' => [
                'id' => $site->id,
                'nom' => $site->nom,
                'description' => $site->description,
                'code_public' => $site->code_public,
                'actif' => $site->actif,
                'created_at' => $site->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function edit(PointageSite $site): Response
    {
        return Inertia::render('pointage/sites/Edit', [
            'site' => [
                'id' => $site->id,
                'nom' => $site->nom,
                'description' => $site->description,
                'code_public' => $site->code_public,
                'actif' => $site->actif,
            ],
        ]);
    }

    public function update(Request $request, PointageSite $site): RedirectResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $site->update([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'actif' => array_key_exists('actif', $validated) ? (bool) $validated['actif'] : $site->actif,
        ]);

        return redirect()->route('pointage.sites.index')
            ->with('success', 'Site mis à jour.');
    }

    public function destroy(PointageSite $site): RedirectResponse
    {
        if ($site->pointages()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des pointages sont déjà associés à ce site.');
        }

        $site->delete();

        return redirect()->route('pointage.sites.index')
            ->with('success', 'Site supprimé.');
    }

    public function regenererQr(PointageSite $site): RedirectResponse
    {
        $site->update([
            'secret_token' => Str::random(40),
            'code_public' => Str::lower(Str::random(14)),
        ]);

        return back()->with('success', 'Code public et jeton de sécurité régénérés. Mettez à jour les affichages / QR sur le site physique.');
    }
}
