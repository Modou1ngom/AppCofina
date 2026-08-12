<?php

namespace App\Models;

use App\Helpers\FilialeHelper;
use App\Traits\HasFilialeScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;

class SigPersonneLiee extends Model
{
    use HasFilialeScope;

    protected static function booted(): void
    {
        static::creating(function (SigPersonneLiee $personne): void {
            if (! $personne->filiale_id) {
                $filialeId = FilialeHelper::getCurrentFilialeId();
                if ($filialeId) {
                    $personne->filiale_id = (int) $filialeId;
                }
            }
        });

        static::saved(function (SigPersonneLiee $personneLiee): void {
            if (! $personneLiee->wasChanged('encours_credit')) {
                return;
            }
            $personneLiee->loadMissing('staffs');
            foreach ($personneLiee->staffs as $staff) {
                $staff->synchroniserEncoursTotaux();
            }
        });
    }

    protected $table = 'sig_personnes_liees';

    protected $fillable = [
        'filiale_id',
        'numero_client',
        'est_personne_morale',
        'prenom',
        'nom',
        'raison_sociale',
        'kyc_piece_identite',
        'kyc_adresse',
        'kyc_telephone',
        'encours_credit',
    ];

    protected function casts(): array
    {
        return [
            'est_personne_morale' => 'boolean',
            'encours_credit' => 'decimal:2',
        ];
    }

    public function filiale(): BelongsTo
    {
        return $this->belongsTo(Filiale::class, 'filiale_id');
    }

    public function staffs(): BelongsToMany
    {
        return $this->belongsToMany(SigStaff::class, 'sig_personne_liee_sig_staff', 'sig_personne_liee_id', 'sig_staff_id')
            ->withPivot(['type_relation', 'classe'])
            ->withTimestamps();
    }

    public function getNomAffichageAttribute(): string
    {
        if ($this->est_personne_morale) {
            return $this->raison_sociale ?: trim(($this->prenom ?? '').' '.($this->nom ?? ''));
        }

        return trim(($this->prenom ?? '').' '.($this->nom ?? ''));
    }

    /**
     * Encours crédit issu du SI (plusieurs alias possibles selon la source SQL / proxy).
     *
     * @param  array<string, mixed>  $siData
     */
    public static function encoursFromSiPayload(array $siData): ?float
    {
        foreach (['encours_total', 'encours_total_m', 'encours_balance', 'total_encours', 'encours', 'sum_encours'] as $k) {
            if (! array_key_exists($k, $siData) || $siData[$k] === null || $siData[$k] === '') {
                continue;
            }
            $raw = $siData[$k];

            return is_numeric($raw) ? (float) $raw : (float) str_replace(',', '.', (string) $raw);
        }

        return null;
    }

    /**
     * Garantit une ligne locale pour ce numéro client : création depuis le SI si elle n’existait pas encore.
     * Les collaborateurs n’ont pas à « créer » une fiche : la saisie du matricule côté liaison suffit.
     * À chaque résolution SI, l’encours est resynchronisé si le SI le fournit (fiche déjà existante comprise).
     *
     * @param  array<string, mixed>  $siData  Résultat de {@see SigSiLookupService::lookup()}
     */
    public static function ensureFromSiData(array $siData): self
    {
        $numero = trim((string) ($siData['matricule'] ?? ''));
        if ($numero === '') {
            throw new \InvalidArgumentException('Données SI invalides : numéro client vide.');
        }

        $existant = static::query()->where('numero_client', $numero)->first();
        if ($existant !== null) {
            $enc = static::encoursFromSiPayload($siData);
            if ($enc !== null && round((float) $existant->encours_credit, 2) !== round($enc, 2)) {
                $existant->encours_credit = $enc;
                $existant->save();
            }

            return $existant;
        }

        $morale = ($siData['type_client'] ?? 'personnel') === 'entreprise';

        $pieceType = (string) ($siData['piece_type'] ?? ($morale ? 'RCCM' : 'CNI'));
        $pieceNum = $siData['piece_numero'] ?? null;
        $kycPiece = ($pieceNum !== null && trim((string) $pieceNum) !== '')
            ? $pieceType.' — '.$pieceNum
            : $pieceType;

        $enc = static::encoursFromSiPayload($siData) ?? 0.0;
        $filialeId = FilialeHelper::getCurrentFilialeId();

        if ($morale) {
            $rs = trim((string) ($siData['raison_sociale'] ?? $siData['prenom_nom'] ?? ''));
            $defaults = [
                'filiale_id' => $filialeId,
                'est_personne_morale' => true,
                'prenom' => null,
                'nom' => null,
                'raison_sociale' => $rs !== '' ? $rs : null,
                'kyc_piece_identite' => $kycPiece,
                'kyc_adresse' => $siData['adresse'] ?? null,
                'kyc_telephone' => $siData['telephone'] ?? null,
                'encours_credit' => $enc,
            ];
        } else {
            $prenom = isset($siData['prenom']) ? trim((string) $siData['prenom']) : '';
            $nom = isset($siData['nom']) ? trim((string) $siData['nom']) : '';
            $defaults = [
                'filiale_id' => $filialeId,
                'est_personne_morale' => false,
                'prenom' => $prenom !== '' ? $prenom : null,
                'nom' => $nom !== '' ? $nom : null,
                'raison_sociale' => null,
                'kyc_piece_identite' => $kycPiece,
                'kyc_adresse' => $siData['adresse'] ?? null,
                'kyc_telephone' => $siData['telephone'] ?? null,
                'encours_credit' => $enc,
            ];
        }

        try {
            return static::query()->firstOrCreate(
                [
                    'numero_client' => $numero,
                    'filiale_id' => $filialeId,
                ],
                $defaults
            );
        } catch (QueryException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'duplicate') || str_contains($msg, 'unique')) {
                return static::query()->where('numero_client', $numero)->firstOrFail();
            }

            throw $e;
        }
    }
}
