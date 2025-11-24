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
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function hasRole(string $roleSlug): bool
    {
        $profil = $this->profil;
        if (!$profil) {
            return false;
        }

        return $profil->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Vérifie si l'utilisateur a au moins un des rôles spécifiés
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        $profil = $this->profil;
        if (!$profil) {
            return false;
        }

        return $profil->roles()->whereIn('slug', $roleSlugs)->exists();
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
     * Récupère tous les rôles de l'utilisateur
     */
    public function getRoles(): \Illuminate\Support\Collection
    {
        $profil = $this->profil;
        if (!$profil) {
            return collect([]);
        }

        return $profil->roles;
    }
}
