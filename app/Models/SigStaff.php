<?php

namespace App\Models;

use App\Helpers\FilialeHelper;
use App\Notifications\SuiviSignatureEncoursSeuilDepasseNotification;
use App\Traits\HasFilialeScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

class SigStaff extends Model
{
    use HasFilialeScope;

    protected $table = 'sig_staffs';

    protected static function booted(): void
    {
        static::creating(function (SigStaff $staff): void {
            if ($staff->filiale_id) {
                return;
            }
            $filialeId = FilialeHelper::getCurrentFilialeId();
            if (! $filialeId && $staff->profile_id) {
                $filialeId = Profil::query()->whereKey($staff->profile_id)->value('filiale_id');
            }
            if ($filialeId) {
                $staff->filiale_id = (int) $filialeId;
            }
        });
    }

    protected $fillable = [
        'filiale_id',
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

    public function filiale(): BelongsTo
    {
        return $this->belongsTo(Filiale::class, 'filiale_id');
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

    /**
     * Fonds propres de référence : paramètre global, sinon valeur de la fiche.
     */
    public function fondsPropresReference(): ?float
    {
        $global = SigParametre::current()->fondsPropresReference();
        if ($global !== null) {
            return $global;
        }

        if ($this->fonds_propres === null) {
            return null;
        }

        $fp = (float) $this->fonds_propres;

        return $fp > 0 ? $fp : null;
    }

    public function tauxEncoursFondsPropres(): ?float
    {
        $fp = $this->fondsPropresReference();
        if ($fp === null || $fp <= 0) {
            return null;
        }

        return round(($this->encoursTotal() / $fp) * 100, 2);
    }

    public function depasseSeuilEncours(?float $seuilPct = null): bool
    {
        $seuil = $seuilPct ?? SigParametre::current()->seuilTauxPct();
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

        $seuil = SigParametre::current()->seuilTauxPct();
        $fp = $this->fondsPropresReference() ?? 0.0;
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
     * Photographie l’état réglementaire de la fiche après un changement des fonds propres de référence.
     */
    public function photographierApresChangementFondsPropres(
        ?int $userId,
        ?float $ancienFondsPropres,
        ?float $nouveauxFondsPropres,
    ): void {
        $this->loadMissing('personnesLiees');
        $this->refresh();

        $params = SigParametre::current();
        $seuil = $params->seuilTauxPct();
        $fp = $nouveauxFondsPropres ?? $this->fondsPropresReference();
        $encCons = $this->encoursTotal();
        $taux = ($fp !== null && $fp > 0) ? round(($encCons / $fp) * 100, 2) : null;

        $fmt = static function (?float $v): string {
            if ($v === null) {
                return 'non renseignés';
            }

            return number_format($v, 0, ',', ' ');
        };

        SigStaffEncoursConformiteEvent::query()->create([
            'sig_staff_id' => $this->id,
            'user_id' => $userId,
            'type' => SigStaffEncoursConformiteEvent::TYPE_CHANGEMENT_FONDS_PROPRES,
            'fonds_propres' => $fp,
            'encours_consolide' => $encCons,
            'taux_pct' => $taux,
            'seuil_pct' => $seuil,
            'commentaire' => sprintf(
                'Changement des fonds propres de référence : %s → %s.',
                $fmt($ancienFondsPropres),
                $fmt($nouveauxFondsPropres)
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function metriquesEncoursPourVue(): array
    {
        $this->loadMissing('personnesLiees');
        $params = SigParametre::current();
        $liees = $this->sommeEncoursPersonnesLiees();
        $staffSi = (float) $this->encours_staff_si;
        $total = $this->encoursTotal();
        $taux = $this->tauxEncoursFondsPropres();
        $seuil = $params->seuilTauxPct();
        $fp = $this->fondsPropresReference();
        $plafond = ($fp !== null && $fp > 0) ? round($fp * ($seuil / 100), 2) : null;
        $ecart = $plafond !== null ? round($plafond - $total, 2) : null;

        return [
            'encours_staff_si' => $staffSi,
            'encours_personnes_liees' => $liees,
            'encours_total' => $total,
            'fonds_propres' => $fp,
            'plafond_reglementaire' => $plafond,
            'ecart' => $ecart,
            'taux_encours_pct' => $taux,
            'seuil_taux_pct' => $seuil,
            'alerte_taux_pct' => $params->alerteTauxPct(),
            'statut_conformite' => $params->statutConformitePourRatio($taux),
            'depasse_seuil_encours' => $this->depasseSeuilEncours(),
            'liaison_bloquee_encours' => $this->liaisonPersonnesLieesBloquee(),
        ];
    }

    /**
     * Garantit une fiche staff locale pour le n° client SI : création depuis le SI si absente.
     *
     * @param  array<string, mixed>  $siData  Résultat de {@see \App\Services\SigSiLookupService::lookup()} (+ fallbacks détection)
     */
    public static function ensureFromSiData(array $siData): self
    {
        $numero = trim((string) ($siData['matricule'] ?? ''));
        if ($numero === '') {
            throw new \InvalidArgumentException('Données SI invalides : numéro client staff vide.');
        }

        $existant = static::query()
            ->where(function ($q) use ($numero) {
                $q->where('numero_client_si', $numero)
                    ->orWhere('reference', $numero);
            })
            ->first();

        if ($existant !== null) {
            $dirty = false;
            if (trim((string) ($existant->numero_client_si ?? '')) === '') {
                $existant->numero_client_si = $numero;
                $dirty = true;
            }
            $enc = SigPersonneLiee::encoursFromSiPayload($siData);
            if ($enc !== null && round((float) $existant->encours_staff_si, 2) !== round($enc, 2)) {
                $existant->encours_staff_si = $enc;
                $dirty = true;
            }
            if ($dirty) {
                $existant->save();
                $existant->synchroniserEncoursTotaux();
            }

            return $existant;
        }

        $prenom = trim((string) ($siData['prenom'] ?? ''));
        $nom = trim((string) ($siData['nom'] ?? ''));
        $full = trim((string) ($siData['prenom_nom'] ?? $siData['full_name'] ?? ''));
        if ($prenom === '' && $nom === '' && $full !== '') {
            $parts = preg_split('/\s+/', $full) ?: [];
            $nom = (string) ($parts[0] ?? $full);
            $prenom = trim(implode(' ', array_slice($parts, 1)));
        }
        if ($prenom === '') {
            $prenom = '—';
        }
        if ($nom === '') {
            $nom = $full !== '' ? $full : $numero;
        }

        $pieceType = (string) ($siData['piece_type'] ?? 'CNI');
        $pieceNum = $siData['piece_numero'] ?? null;
        $kycPiece = ($pieceNum !== null && trim((string) $pieceNum) !== '')
            ? $pieceType.' — '.$pieceNum
            : $pieceType;

        $enc = SigPersonneLiee::encoursFromSiPayload($siData) ?? 0.0;
        $profileId = $siData['profile_id'] ?? null;
        if ($profileId === '' || $profileId === null) {
            $profil = Profil::query()->where('matricule', $numero)->first();
            $profileId = $profil?->id;
        }

        try {
            $staff = static::query()->create([
                'filiale_id' => FilialeHelper::getCurrentFilialeId()
                    ?? ($profileId ? Profil::query()->whereKey($profileId)->value('filiale_id') : null),
                'reference' => $numero,
                'numero_client_si' => $numero,
                'profile_id' => $profileId,
                'prenom' => $prenom,
                'nom' => $nom,
                'fonction' => $siData['fonction'] ?? null,
                'departement' => $siData['departement'] ?? null,
                'type_personne' => 'staff',
                'statut' => 'actif',
                'kyc_piece_identite' => $kycPiece,
                'kyc_adresse' => $siData['adresse'] ?? null,
                'kyc_telephone' => $siData['telephone'] ?? null,
                'encours_staff_si' => $enc,
                'encours_credit_individuel' => 0,
                'score_risque' => null,
            ]);
        } catch (QueryException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'duplicate') || str_contains($msg, 'unique')) {
                return static::query()
                    ->where(function ($q) use ($numero) {
                        $q->where('numero_client_si', $numero)
                            ->orWhere('reference', $numero);
                    })
                    ->firstOrFail();
            }

            throw $e;
        }

        $staff->synchroniserEncoursTotaux();

        return $staff;
    }
}
