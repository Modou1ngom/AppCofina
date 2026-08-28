<?php

namespace App\Models;

use App\Helpers\FilialeHelper;
use App\Traits\HasFilialeScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnqueteSatisfactionReponse extends Model
{
    use HasFilialeScope;

    protected static function booted(): void
    {
        static::creating(function (EnqueteSatisfactionReponse $reponse): void {
            if (! $reponse->filiale_id) {
                $filialeId = FilialeHelper::getCurrentFilialeId();
                if ($filialeId) {
                    $reponse->filiale_id = (int) $filialeId;
                }
            }
        });
    }

    public const CRITERES = [
        'qualite_accueil_ecoute' => 'Qualité de l’accueil et de l’écoute',
        'rapidite_prise_en_charge' => 'Rapidité de prise en charge',
        'temps_resolution' => 'Temps de résolution',
        'professionnalisme_equipe_it' => 'Professionnalisme de l’équipe IT',
        'qualite_solution' => 'Qualité de la solution apportée',
        'communication_suivi' => 'Communication et suivi',
        'satisfaction_globale' => 'Satisfaction globale',
    ];

    public const RECOMMANDATIONS = [
        'oui' => 'Oui',
        'non' => 'Non',
        'peut_etre' => 'Peut-être',
    ];

    public const QUALITE_PRISE_EN_CHARGE = [
        'tres_satisfait' => 'Très satisfait',
        'satisfait' => 'Satisfait',
        'moyennement_satisfait' => 'Moyennement satisfait',
        'insatisfait' => 'Insatisfait',
    ];

    public const DELAIS_REPONSE = [
        'tres_rapide' => 'Très rapide',
        'rapide' => 'Rapide',
        'acceptable' => 'Acceptable',
        'lent' => 'Lent',
    ];

    protected $fillable = [
        'filiale_id',
        'nom',
        'matricule',
        'service',
        'qualite_accueil_ecoute',
        'rapidite_prise_en_charge',
        'temps_resolution',
        'professionnalisme_equipe_it',
        'qualite_solution',
        'communication_suivi',
        'satisfaction_globale',
        'remarques_difficultes',
        'suggestions_amelioration',
        'besoins_attentes',
        'recommandation',
        'qualite_prise_en_charge',
        'delai_reponse',
        'commentaires_additionnels',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'qualite_accueil_ecoute' => 'integer',
            'rapidite_prise_en_charge' => 'integer',
            'temps_resolution' => 'integer',
            'professionnalisme_equipe_it' => 'integer',
            'qualite_solution' => 'integer',
            'communication_suivi' => 'integer',
            'satisfaction_globale' => 'integer',
        ];
    }

    public function filiale(): BelongsTo
    {
        return $this->belongsTo(Filiale::class, 'filiale_id');
    }

    public function moyenneNotes(): float
    {
        $notes = collect(self::CRITERES)->keys()->map(fn (string $key) => (int) $this->{$key});

        return round($notes->avg(), 2);
    }
}
