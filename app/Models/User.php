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
     * Vérifie si l'utilisateur a un rôle spécifique
     * Vérifie d'abord les rôles de l'utilisateur, puis ceux du profil
     */
    public function hasRole(string $roleSlug): bool
    {
        // Vérifier les rôles de l'utilisateur
        if ($this->roles()->where('slug', $roleSlug)->exists()) {
            return true;
        }

        // Vérifier les rôles du profil si disponible
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

        // Vérifier les rôles du profil si disponible
        if ($this->profil) {
            return $this->profil->roles()->whereIn('slug', $roleSlugs)->exists();
        }

        return false;
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
        if (!$this->relationLoaded('profil')) {
            $this->load('profil');
        }

        if (!$this->profil) {
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
     * Vérifie si l'utilisateur est responsable d'un département
     */
    public function isResponsableDepartement(): bool
    {
        if (!$this->profil) {
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
        if (!$this->profil) {
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
