<?php

namespace App\Models;

use App\Notifications\SuiviSignatureEncoursSeuilDepasseNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class SigStaff extends Model
{
    protected $table = 'sig_staffs';

    protected $fillable = [
        'reference',
        'numero_client_si',
        'profile_id',
        'prenom',
        'nom',
        'fonction',
        'departement',
        'type_personne',
        'statut',
        'kyc_piece_identite',
        'kyc_adresse',
        'kyc_telephone',
        'encours_staff_si',
        'encours_credit_individuel',
        'fonds_propres',
        'score_risque',
    ];

    protected function casts(): array
    {
        return [
            'encours_staff_si' => 'decimal:2',
            'encours_credit_individuel' => 'decimal:2',
            'fonds_propres' => 'decimal:2',
            'score_risque' => 'decimal:2',
            'encours_conformite_en_depassement' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'profile_id');
    }

    /**
     * Collaborateur « staff » : le numéro client SI doit être renseigné (lookup KYC + encours propre) avant toute nouvelle liaison.
     */
    public function doitSynchroniserClientSiAvantLiens(): bool
    {
        if ($this->type_personne !== 'staff') {
            return false;
        }

        return trim((string) ($this->numero_client_si ?? '')) === '';
    }

    public function personnesLiees(): BelongsToMany
    {
        return $this->belongsToMany(SigPersonneLiee::class, 'sig_personne_liee_sig_staff', 'sig_staff_id', 'sig_personne_liee_id')
            ->withPivot(['type_relation', 'classe'])
            ->withTimestamps();
    }

    /**
     * Historique automatique (dépassement / retour sous seuil) et traces manuelles (conformité).
     */
    public function conformiteEncoursEvents(): HasMany
    {
        return $this->hasMany(SigStaffEncoursConformiteEvent::class, 'sig_staff_id');
    }

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }

    public function sommeEncoursPersonnesLiees(): float
    {
        return (float) $this->personnesLiees()->sum('encours_credit');
    }

    /**
     * Encours total réglementaire = encours propre (SI) + somme des encours des personnes liées.
     */
    public function encoursTotal(): float
    {
        return round((float) $this->encours_staff_si + $this->sommeEncoursPersonnesLiees(), 2);
    }

    public function tauxEncoursFondsPropres(): ?float
    {
        $fp = (float) ($this->fonds_propres ?? 0);
        if ($fp <= 0) {
            return null;
        }

        return round(($this->encoursTotal() / $fp) * 100, 2);
    }

    public function depasseSeuilEncours(?float $seuilPct = null): bool
    {
        $seuil = $seuilPct ?? (float) config('sig.encours_taux_seuil_pct', 10);
        $taux = $this->tauxEncoursFondsPropres();
        if ($taux === null) {
            return false;
        }

        return $taux > $seuil;
    }

    /**
     * Nouvelles liaisons interdites si le taux dépasse le seuil (fonds propres requis pour le calcul).
     */
    public function liaisonPersonnesLieesBloquee(): bool
    {
        return $this->depasseSeuilEncours();
    }

    /**
     * Met à jour encours_credit_individuel (= encours total) et notifie si le seuil est dépassé.
     */
    public function synchroniserEncoursTotaux(): void
    {
        $total = $this->encoursTotal();

        if (round((float) $this->encours_credit_individuel, 2) !== round($total, 2)) {
            $this->forceFill(['encours_credit_individuel' => $total])->saveQuietly();
        }

        $this->maybeNotifyDepassementEncours();
        $this->enregistrerTransitionConformiteEncours();
    }

    /**
     * Historise les passages sous / au-dessus du seuil (fonds propres vs encours consolidé).
     * Les commentaires sont ajoutés via {@see SigEncoursConformiteController::storeCommentaire()}.
     */
    private function enregistrerTransitionConformiteEncours(): void
    {
        $this->refresh();

        $seuil = (float) config('sig.encours_taux_seuil_pct', 10);
        $fp = $this->fonds_propres !== null ? (float) $this->fonds_propres : 0.0;
        $encCons = $this->encoursTotal();
        $taux = $this->tauxEncoursFondsPropres();
        $depasse = $this->depasseSeuilEncours($seuil);
        $prev = (bool) $this->encours_conformite_en_depassement;

        if ($fp > 0 && $depasse && ! $prev) {
            SigStaffEncoursConformiteEvent::query()->create([
                'sig_staff_id' => $this->id,
                'user_id' => null,
                'type' => SigStaffEncoursConformiteEvent::TYPE_DEPASSEMENT,
                'fonds_propres' => $fp,
                'encours_consolide' => $encCons,
                'taux_pct' => $taux,
                'seuil_pct' => $seuil,
                'commentaire' => null,
            ]);
            $this->forceFill(['encours_conformite_en_depassement' => true])->saveQuietly();

            return;
        }

        if ($prev && (! $depasse || $fp <= 0)) {
            SigStaffEncoursConformiteEvent::query()->create([
                'sig_staff_id' => $this->id,
                'user_id' => null,
                'type' => SigStaffEncoursConformiteEvent::TYPE_RETOUR_CONFORME,
                'fonds_propres' => $fp > 0 ? $fp : null,
                'encours_consolide' => $encCons,
                'taux_pct' => $fp > 0 ? $taux : null,
                'seuil_pct' => $seuil,
                'commentaire' => $fp <= 0 ? 'Fonds propres non renseignés : le contrôle taux n’est plus évalué.' : null,
            ]);
            $this->forceFill(['encours_conformite_en_depassement' => false])->saveQuietly();
        }
    }

    private function maybeNotifyDepassementEncours(): void
    {
        $this->refresh();

        if (! $this->depasseSeuilEncours()) {
            return;
        }

        if (! Cache::add('sig_encours_notif_'.$this->id.'_'.now()->format('Y-m-d'), 1, now()->endOfDay())) {
            return;
        }

        $this->loadMissing('profile');
        if (! $this->profile?->email) {
            return;
        }

        $user = User::query()->where('email', $this->profile->email)->first();
        if ($user) {
            $user->notify(new SuiviSignatureEncoursSeuilDepasseNotification($this));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function metriquesEncoursPourVue(): array
    {
        $this->loadMissing('personnesLiees');
        $liees = $this->sommeEncoursPersonnesLiees();
        $staffSi = (float) $this->encours_staff_si;
        $total = $this->encoursTotal();
        $taux = $this->tauxEncoursFondsPropres();
        $seuil = (float) config('sig.encours_taux_seuil_pct', 10);

        return [
            'encours_staff_si' => $staffSi,
            'encours_personnes_liees' => $liees,
            'encours_total' => $total,
            'fonds_propres' => $this->fonds_propres !== null ? (float) $this->fonds_propres : null,
            'taux_encours_pct' => $taux,
            'seuil_taux_pct' => $seuil,
            'depasse_seuil_encours' => $this->depasseSeuilEncours(),
            'liaison_bloquee_encours' => $this->liaisonPersonnesLieesBloquee(),
        ];
    }
}
