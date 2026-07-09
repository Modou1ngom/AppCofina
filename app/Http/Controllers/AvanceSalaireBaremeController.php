<?php

namespace App\Http\Controllers;

use App\Models\AvanceSalaireBareme;
use App\Models\AvanceSalaireDemande;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AvanceSalaireBaremeController extends Controller
{
    public function index(): Response
    {
        $baremes = AvanceSalaireBareme::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (AvanceSalaireBareme $b) => [
                'id' => $b->id,
                'key' => $b->key,
                'label' => $b->label,
                'compte_charge' => $b->compte_charge,
                'code_operation' => $b->code_operation,
                'duree_max_mois' => $b->duree_max_mois,
                'plafond_non_cadre' => (float) $b->plafond_non_cadre,
                'plafond_cadre' => (float) $b->plafond_cadre,
                'plafond_emc' => (float) $b->plafond_emc,
                'sort_order' => $b->sort_order,
                'is_active' => $b->is_active,
            ]);

        return Inertia::render('avances-salaire/Parametrage', [
            'baremes' => $baremes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        AvanceSalaireBareme::create($validated);

        return redirect()->back()->with('success', 'Barème ajouté.');
    }

    public function update(Request $request, AvanceSalaireBareme $bareme): RedirectResponse
    {
        $validated = $request->validate($this->rules($bareme->id));

        $bareme->update($validated);

        return redirect()->back()->with('success', 'Barème mis à jour.');
    }

    public function destroy(AvanceSalaireBareme $bareme): RedirectResponse
    {
        if (AvanceSalaireDemande::query()->where('type_avance', $bareme->key)->exists()) {
            return redirect()->back()->with('error', 'Suppression impossible : ce type est déjà utilisé dans des demandes.');
        }

        $bareme->delete();

        return redirect()->back()->with('success', 'Barème supprimé.');
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'key' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9_]+$/', Rule::unique('avance_salaire_baremes', 'key')->ignore($ignoreId)],
            'label' => ['required', 'string', 'max:128'],
            'compte_charge' => ['nullable', 'string', 'max:64'],
            'code_operation' => ['nullable', 'string', 'max:32'],
            'duree_max_mois' => ['required', 'integer', 'min:1', 'max:24'],
            'plafond_non_cadre' => ['required', 'numeric', 'min:0'],
            'plafond_cadre' => ['required', 'numeric', 'min:0'],
            'plafond_emc' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
