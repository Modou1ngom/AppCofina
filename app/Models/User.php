<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Relation avec le profil (via email)
     */
    public function profil()
    {
        return $this->hasOne(Profil::class, 'email', 'email');
    }

    public function pointages()
    {
        return $this->hasMany(Pointage::class);
    }

    public function pointageDeclarations()
    {
        return $this->hasMany(PointageDeclaration::class);
    }

    /**
     * Fiche collaborateur liée au compte : même logique que {@see profil()}, avec correspondance
     * d’e-mail insensible à la casse et aux espaces (évite les écarts import / compte).
     *
     * @return $this
     */
    public function profilCollaborateurAssocie(): self
    {
        $this->loadMissing('profil');
        if ($this->profil !== null) {
            return $this;
        }

        $email = strtolower(trim((string) $this->email));
        if ($email === '') {
            return $this;
        }

        $found = Profil::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if ($found === null) {
            $profileId = AvanceSalaireDemande::query()
                ->where('user_id', $this->id)
                ->whereNotNull('profile_id')
                ->latest('id')
                ->value('profile_id');

            if ($profileId) {
                $found = Profil::query()->find($profileId);
            }
        }

        if ($found !== null) {
            $this->setRelation('profil', $found);
        }

        return $this;
    }

    /**
     * Relation avec les rôles (many-to-many)
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
    }

    /**
     * Relation avec les filiales/environnements (many-to-many)
     */
    public function filiales()
    {
        return $this->belongsToMany(Filiale::class, 'user_filiale', 'user_id', 'filiale_id');
    }

    /**
     * Relation avec les agences (many-to-many).
     */
    public function agences()
    {
        return $this->belongsToMany(Agence::class, 'agence_user', 'user_id', 'agence_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /**
     * Retourne l'agence domiciliaire (par défaut) de l'utilisateur.
     */
    public function agenceDomiciliaire(): ?Agence
    {
        return $this->agences->firstWhere('pivot.is_default', true);
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     * Vérifie d'abord les rôles de l'utilisateur, puis ceux du profil
     */
    public function hasRole(string $roleSlug): bool
    {
        // Vérifier les rôles de l'utilisateur
        if ($this->roles()->where('slug', $roleSlug)->exists()) {
            return true;
        }

        $this->profilCollaborateurAssocie();

        if ($this->profil) {
            return $this->profil->roles()->where('slug', $roleSlug)->exists();
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur a au moins un des rôles spécifiés
     * Vérifie d'abord les rôles de l'utilisateur, puis ceux du profil
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        // Vérifier les rôles de l'utilisateur
        if ($this->roles()->whereIn('slug', $roleSlugs)->exists()) {
            return true;
        }

        $this->profilCollaborateurAssocie();

        if ($this->profil) {
            return $this->profil->roles()->whereIn('slug', $roleSlugs)->exists();
        }

        return false;
    }

    /**
     * Filiale unique accessible (hors super admin).
     * Priorité : filiale du profil collaborateur, puis pivot user_filiale.
     */
    public function primaryFilialeId(): ?int
    {
        $this->profilCollaborateurAssocie();

        if ($this->profil?->filiale_id) {
            return (int) $this->profil->filiale_id;
        }

        $pivotId = $this->filiales()->orderBy('filiales.id')->value('filiales.id');

        return $pivotId ? (int) $pivotId : null;
    }

    /**
     * Filiales visibles : null = toutes (super admin), sinon une seule filiale ou aucune.
     *
     * @return list<int>|null
     */
    public function allowedFilialeIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $id = $this->primaryFilialeId();

        return $id !== null ? [$id] : [];
    }

    public function canAccessFiliale(?int $filialeId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($filialeId === null) {
            return false;
        }

        $allowed = $this->allowedFilialeIds();

        return in_array($filialeId, $allowed ?? [], true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public function applyFilialeScopeToQuery($query, string $column = 'filiale_id'): void
    {
        $allowed = $this->allowedFilialeIds();

        if ($allowed === null) {
            return;
        }

        if ($allowed === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn($column, $allowed);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Profil>  $query
     */
    public function applyProfilVisibilityScope($query): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        $allowed = $this->allowedFilialeIds();

        if ($this->isAdmin() || $this->isRh()) {
            $this->applyFilialeScopeToQuery($query);

            return;
        }

        $this->profilCollaborateurAssocie();
        $profil = $this->profil;

        if (! $profil) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function ($q) use ($profil) {
            $q->where('id', $profil->id)
                ->orWhere('n_plus_1_id', $profil->id);
        });

        if ($allowed !== []) {
            $query->whereIn('filiale_id', $allowed);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\User>  $query
     */
    public function applyUserVisibilityScope($query): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        $allowed = $this->allowedFilialeIds();

        if ($allowed === []) {
            $query->where('users.id', $this->id);

            return;
        }

        $query->where(function ($q) use ($allowed) {
            $q->where('users.id', $this->id)
                ->orWhereHas('profil', fn ($sub) => $sub->whereIn('filiale_id', $allowed))
                ->orWhereHas('filiales', fn ($sub) => $sub->whereIn('filiales.id', $allowed));
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Habilitation>  $query
     */
    public function applyHabilitationFilialeScope($query): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        $allowed = $this->allowedFilialeIds();

        if ($allowed === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function ($q) use ($allowed) {
            $q->whereHas('requester', fn ($sub) => $sub->whereIn('filiale_id', $allowed))
                ->orWhereHas('beneficiary', fn ($sub) => $sub->whereIn('filiale_id', $allowed));
        });
    }

    public function canAccessUser(User $target): bool
    {
        if ($this->isSuperAdmin() || $this->id === $target->id) {
            return true;
        }

        $target->loadMissing('profil');

        if ($target->profil?->filiale_id && $this->canAccessFiliale((int) $target->profil->filiale_id)) {
            return true;
        }

        $targetFilialeIds = $target->filiales()->pluck('filiales.id')->map(fn ($id) => (int) $id)->all();
        $allowed = $this->allowedFilialeIds() ?? [];

        return ! empty(array_intersect($allowed, $targetFilialeIds));
    }

    public function canAccessProfil(?Profil $profil): bool
    {
        if (! $profil) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isAdmin() || $this->isRh()) {
            return $this->canAccessFiliale($profil->filiale_id ? (int) $profil->filiale_id : null);
        }

        $this->profilCollaborateurAssocie();
        $userProfil = $this->profil;

        if ($userProfil && ($profil->id === $userProfil->id || $profil->n_plus_1_id === $userProfil->id)) {
            return $profil->filiale_id === null || $this->canAccessFiliale((int) $profil->filiale_id);
        }

        return false;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Filiale>
     */
    public function visibleFilialesQuery()
    {
        $query = Filiale::query()->where('actif', true)->orderBy('nom');
        $this->applyFilialeScopeToQuery($query, 'id');

        return $query;
    }

    /**
     * Vérifie si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->isSuperAdmin();
    }

    /**
     * Vérifie si l'utilisateur est super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Vérifie si l'utilisateur est métier
     */
    public function isMetier(): bool
    {
        return $this->hasRole('metier');
    }

    /**
     * Vérifie si l'utilisateur est contrôle
     */
    public function isControle(): bool
    {
        return $this->hasRole('controle');
    }

    /**
     * Vérifie si l'utilisateur est RH
     */
    public function isRh(): bool
    {
        return $this->hasRole('rh');
    }

    /**
     * Responsable RH (DRH) — signature des ordres de mission.
     */
    public function isResponsableRh(): bool
    {
        return $this->hasRole('responsable_rh');
    }

    public function isFinance(): bool
    {
        return $this->hasRole('finance');
    }

    /**
     * Profil logistique — traitement des demandes au niveau Facilities.
     */
    public function isLogistique(): bool
    {
        return $this->hasAnyRole(['logistique', 'facilities']);
    }

    public function isFacilities(): bool
    {
        return $this->isLogistique();
    }

    public function isMd(): bool
    {
        return $this->hasRole('md');
    }

    public function isDga(): bool
    {
        return $this->hasRole('dga');
    }

    /**
     * Profil rattaché au département RH (collaborateurs RH hors rôle métier).
     */
    public function estDansDepartementRh(): bool
    {
        if ($this->isRh() || $this->isResponsableRh()) {
            return true;
        }

        return $this->departementCorrespond('/\b(rh|ressources?\s*humaines|drh)\b/i');
    }

    public function estDansDepartementDga(): bool
    {
        if ($this->isDga()) {
            return true;
        }

        return $this->departementCorrespond('/\b(dga|direction\s+g[eé]n[eé]rale\s+adjointe?)\b/i');
    }

    public function estDansDepartementMd(): bool
    {
        if ($this->isMd()) {
            return true;
        }

        return $this->departementCorrespond('/\b(md|directeur\s+g[eé]n[eé]ral|direction\s+g[eé]n[eé]rale)\b/i');
    }

    public function estDansDepartementLogistique(): bool
    {
        if ($this->isLogistique()) {
            return true;
        }

        return $this->departementCorrespond('/\b(logistique|facilities)\b/i');
    }

    public function estDansDepartementFinance(): bool
    {
        if ($this->isFinance()) {
            return true;
        }

        return $this->departementCorrespond('/\b(finance|financier|comptabilit[eé])\b/i');
    }

    /**
     * Récap logistique : rôles RH, RRH, Facilities/Logistique, Finance, DGA, MD
     * ou départements logistique, finance et RH.
     */
    public function peutVoirRecapLogistique(): bool
    {
        return $this->isAdmin()
            || $this->isRh()
            || $this->isResponsableRh()
            || $this->isLogistique()
            || $this->isFinance()
            || $this->isDga()
            || $this->isMd()
            || $this->estDansDepartementRh()
            || $this->estDansDepartementLogistique()
            || $this->estDansDepartementFinance();
    }

    private function departementCorrespond(string $pattern): bool
    {
        $this->loadMissing('profil');
        $departement = strtolower(trim((string) ($this->profil?->departement ?? '')));
        if ($departement === '') {
            return false;
        }

        return (bool) preg_match($pattern, $departement);
    }

    public function avanceSalaireDemandes()
    {
        return $this->hasMany(AvanceSalaireDemande::class, 'user_id');
    }

    /**
     * Vérifie si l'utilisateur est conformité
     */
    public function isConformite(): bool
    {
        return $this->hasRole('conformite');
    }

    /**
     * Fiche suivi signature (personnes apparentées) liée au profil RH de l'utilisateur.
     */
    public function sigStaffFiche(): ?SigStaff
    {
        $this->loadMissing('profil');
        if (! $this->profil) {
            return null;
        }

        return SigStaff::query()->where('profile_id', $this->profil->id)->first();
    }

    /**
     * Peut déclarer des personnes liées via le SI (admin / conformité : tout le module ;
     * collaborateur : dès qu’un profil RH est lié au compte, y compris avant création de la fiche staff).
     */
    public function peutDeclarerPersonnesLieesSig(): bool
    {
        if ($this->isAdmin() || $this->isConformite()) {
            return true;
        }

        $this->loadMissing('profil');

        return $this->profil !== null;
    }

    /**
     * Vérifie si l'utilisateur est exécuteur IT (basé sur le profil)
     * Note: "informatique" est automatiquement normalisé en "IT" via les accessors du modèle Profil
     */
    public function isExecuteurIt(): bool
    {
        // Vérifier d'abord les rôles pour compatibilité
        if ($this->hasRole('executeur_it') || $this->hasRole('it')) {
            return true;
        }

        // Recharger le profil si nécessaire
        if (! $this->relationLoaded('profil')) {
            $this->load('profil');
        }

        if (! $this->profil) {
            return false;
        }

        $profil = $this->profil;

        // Vérifier si le département contient "IT" ou "informatique"
        // On vérifie la valeur brute directement pour éviter les problèmes avec les accessors
        $departement = $profil->getRawOriginal('departement') ?? $profil->departement;
        if ($departement) {
            $departementLower = strtolower($departement);
            // Vérifier "it" comme mot entier (pas dans "capital", "spirit", etc.)
            if (preg_match('/\b(it|informatique|technique)\b/i', $departementLower)) {
                return true;
            }
        }

        // Vérifier si la fonction contient "IT" ou "informatique"
        $fonction = $profil->getRawOriginal('fonction') ?? $profil->fonction;
        if ($fonction) {
            $fonctionLower = strtolower($fonction);
            // Vérifier "it" comme mot entier (pas dans "capital", "spirit", etc.)
            if (preg_match('/\b(it|informatique|technique)\b/i', $fonctionLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur est Head IT
     */
    public function isHeadIt(): bool
    {
        return $this->hasRole('head_it') || $this->hasRole('chef_it');
    }

    /**
     * Vérifie si l'utilisateur est Audit
     */
    public function isAudit(): bool
    {
        return $this->hasRole('audit') || $this->hasRole('direction_audit');
    }

    /**
     * Consultation de l'historique des workflows mission (profils IT ou Audit).
     * IT : rôle « it » ou profil RH rattaché au département / fonction informatique.
     */
    public function peutVoirHistoriqueMissions(): bool
    {
        return $this->isExecuteurIt() || $this->isAudit();
    }

    /**
     * Au moins un profil collaborateur a cet utilisateur comme N+1 (champ n_plus_1_id).
     */
    public function estDesigneN1DunProfil(): bool
    {
        $this->profilCollaborateurAssocie();

        $profilId = $this->profil?->id;
        if ($profilId === null) {
            return false;
        }

        return Profil::query()
            ->where('n_plus_1_id', $profilId)
            ->exists();
    }

    /**
     * Vérifie si l'utilisateur est responsable d'un département
     */
    public function isResponsableDepartement(): bool
    {
        if (! $this->profil) {
            return false;
        }

        return \App\Models\Departement::where('responsable_departement_id', $this->profil->id)
            ->where('actif', true)
            ->exists();
    }

    /**
     * Récupère le département dont l'utilisateur est responsable
     */
    public function getDepartementResponsable()
    {
        if (! $this->profil) {
            return null;
        }

        return \App\Models\Departement::where('responsable_departement_id', $this->profil->id)
            ->where('actif', true)
            ->first();
    }

    /**
     * Récupère tous les rôles de l'utilisateur
     */
    public function getRoles(): \Illuminate\Support\Collection
    {
        return $this->roles;
    }
}
