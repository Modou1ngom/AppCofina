<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profil extends Model
{
    use HasFactory;

    public const POINTAGE_EN_ATTENTE = 'en_attente';

    public const POINTAGE_PRIS_EN_CHARGE = 'pris_en_charge';

    protected $table = 'profiles';

    protected $fillable = [
        'matricule',
        'matricule_sirh',
        'prenom',
        'nom',
        'entite',
        'nationalite',
        'fonction',
        'categorie',
        'departement',
        'email',
        'numero_carte_assurance',
        'telephone',
        'site',
        'genre',
        'date_naissance',
        'diplome',
        'age',
        'situation_matrimoniale',
        'nombre_enfants',
        'numero_cni',
        'numero_compte',
        'code_agence',
        'type_contrat',
        'duree_contrat',
        'date_debut_contrat',
        'date_fin_contrat',
        'date_embauche',
        'statut',
        'pointage_statut',
        'pointage_demande_at',
        'pointage_demande_par',
        'pointage_confirme_at',
        'pointage_confirme_par',
        'pointage_commentaire',
        'statut_rh',
        'type_office',
        'n_plus_1_id',
        'n_plus_2_id',
        'filiale_id',
        'date_entree',
        'dossier_a_jour',
        'anciennete',
        'date_sortie',
        'motif_depart',
        'grade',
        'h',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_debut_contrat' => 'date',
        'date_fin_contrat' => 'date',
        'date_embauche' => 'date',
        'date_entree' => 'date',
        'date_sortie' => 'date',
        'dossier_a_jour' => 'boolean',
        'age' => 'integer',
        'nombre_enfants' => 'integer',
        'pointage_demande_at' => 'datetime',
        'pointage_confirme_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Profil $profil): void {
            if (! $profil->exists && blank($profil->date_entree)) {
                $profil->date_entree = now()->toDateString();
            }

            $profil->syncDerivedRhFields();
        });

        static::created(function (Profil $profil): void {
            if ($profil->date_entree || ! $profil->created_at) {
                return;
            }

            $profil->forceFill([
                'date_entree' => $profil->created_at->toDateString(),
            ])->saveQuietly();
        });
    }

    // Relations
    public function nPlus1()
    {
        return $this->belongsTo(Profil::class, 'n_plus_1_id');
    }

    public function nPlus2()
    {
        return $this->belongsTo(Profil::class, 'n_plus_2_id');
    }

    public function subordonnes()
    {
        return $this->hasMany(Profil::class, 'n_plus_1_id');
    }

    // Alias pour compatibilité ascendante
    public function superieurHierarchique()
    {
        return $this->nPlus1();
    }

    public function habilitationsEnTantQueDemandeur()
    {
        return $this->hasMany(Habilitation::class, 'requester_profile_id');
    }

    public function habilitationsEnTantQueBeneficiaire()
    {
        return $this->hasMany(Habilitation::class, 'beneficiary_profile_id');
    }

    public function mouvements()
    {
        return $this->hasMany(ProfilMouvement::class, 'profil_id')->orderByDesc('date_effet')->orderByDesc('id');
    }

    // Méthodes alias pour compatibilité ascendante
    public function habilitationsAsRequester()
    {
        return $this->habilitationsEnTantQueDemandeur();
    }

    public function habilitationsAsBeneficiary()
    {
        return $this->habilitationsEnTantQueBeneficiaire();
    }

    /**
     * Relation avec les rôles (many-to-many)
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'profile_role', 'profile_id', 'role_id');
    }

    /**
     * Relation avec la filiale
     */
    public function filiale()
    {
        return $this->belongsTo(Filiale::class, 'filiale_id');
    }

    public function pointageDemandePar()
    {
        return $this->belongsTo(User::class, 'pointage_demande_par');
    }

    public function pointageConfirmePar()
    {
        return $this->belongsTo(User::class, 'pointage_confirme_par');
    }

    public function pointageStatutLabel(): ?string
    {
        return match ($this->pointage_statut) {
            self::POINTAGE_EN_ATTENTE => 'En attente de pointage',
            self::POINTAGE_PRIS_EN_CHARGE => 'Pointage pris en charge',
            default => null,
        };
    }

    public function getFullNameAttribute()
    {
        return "{$this->prenom} {$this->nom}";
    }

    /**
     * Âge depuis la date de naissance, ancienneté depuis l'embauche ou l'entrée.
     */
    public function syncDerivedRhFields(): void
    {
        if ($this->date_naissance) {
            $this->age = $this->date_naissance->age;
        }

        $start = $this->date_embauche ?? $this->date_entree;
        if ($start) {
            $this->anciennete = self::formatAnciennete($start);
        }
    }

    public static function formatAnciennete(\DateTimeInterface $start): string
    {
        $diff = \Carbon\Carbon::parse($start)->diff(now());
        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y.' an'.($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m.' mois';
        }
        if ($parts === [] && $diff->d > 0) {
            $parts[] = $diff->d.' jour'.($diff->d > 1 ? 's' : '');
        }

        return $parts === [] ? '0 jour' : implode(' ', $parts);
    }

    /**
     * Normalise "informatique" en "IT" pour le département
     */
    public function getDepartementAttribute($value)
    {
        if (! $value) {
            return $value;
        }

        // Normaliser "informatique" en "IT" (insensible à la casse)
        $normalized = preg_replace('/informatique/i', 'IT', $value);

        return $normalized;
    }

    /**
     * Normalise "informatique" en "IT" pour la fonction
     */
    public function getFonctionAttribute($value)
    {
        if (! $value) {
            return $value;
        }

        // Normaliser "informatique" en "IT" (insensible à la casse)
        $normalized = preg_replace('/informatique/i', 'IT', $value);

        return $normalized;
    }

    /**
     * Génère un matricule unique automatiquement
     * Format: M suivi d'un numéro incrémenté (ex: M1, M2, M3, etc.)
     */
    public static function generateMatricule(): string
    {
        $prefix = 'M0';

        // Récupérer tous les matricules qui commencent par "M"
        $matricules = self::where('matricule', 'like', "{$prefix}%")
            ->pluck('matricule')
            ->toArray();

        $maxNumber = 0;

        foreach ($matricules as $matricule) {
            // Extraire le numéro après "M"
            // Gère les formats: M1, M-2025-0001, etc.
            $numberPart = substr($matricule, 1); // Enlève le "M"

            // Si le format est M-YYYY-XXXX, extraire le dernier nombre
            if (preg_match('/-(\d+)$/', $numberPart, $matches)) {
                $number = (int) $matches[1];
            } elseif (preg_match('/^(\d+)/', $numberPart, $matches)) {
                // Format simple M1, M2, etc.
                $number = (int) $matches[1];
            } else {
                // Essayer de convertir directement en extrayant tous les chiffres
                $number = (int) preg_replace('/[^0-9]/', '', $numberPart);
            }

            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }

        $nextNumber = $maxNumber + 1;

        return "{$prefix}{$nextNumber}";
    }
}
