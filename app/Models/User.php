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
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Vérifie si l'utilisateur a au moins un des rôles spécifiés
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()->whereIn('slug', $roleSlugs)->exists();
    }

    /**
     * Vérifie si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
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
     */
    public function isExecuteurIt(): bool
    {
        if (!$this->profil) {
            return false;
        }

        $profil = $this->profil;
        
        // Vérifier si le département contient "IT" ou "informatique"
        if ($profil->departement) {
            $departement = strtolower($profil->departement);
            if (str_contains($departement, 'it') || 
                str_contains($departement, 'informatique') || 
                str_contains($departement, 'technique')) {
                return true;
            }
        }

        // Vérifier si la fonction contient "IT" ou "informatique"
        if ($profil->fonction) {
            $fonction = strtolower($profil->fonction);
            if (str_contains($fonction, 'it') || 
                str_contains($fonction, 'informatique') || 
                str_contains($fonction, 'technique')) {
                return true;
            }
        }

        // Vérifier aussi les rôles pour compatibilité
        return $this->hasRole('executeur_it') || $this->hasRole('it');
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
