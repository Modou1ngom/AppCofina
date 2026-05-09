<?php

namespace App\Services;

use App\Models\Profil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Données REPORT_GROUPE : priorité au proxy HTTP (Python), sinon Oracle direct (oci8 + SQL).
 */
class SigOracleReportGroupeService
{
    public function __construct(
        private readonly SigOracleReportGroupeHttpClient $httpClient
    ) {}

    public function usesHttpProxy(): bool
    {
        return $this->httpClient->isConfigured();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lookupPersonnel(string $matricule): ?array
    {
        $matricule = trim($matricule);
        if ($matricule === '') {
            return null;
        }

        if ($this->httpClient->isConfigured()) {
            $fromHttp = $this->httpClient->lookupPersonnel($matricule);
            if ($fromHttp === null) {
                return null;
            }
            if (isset($fromHttp['prenom_nom'])) {
                return $this->enrichPersonnelAvecProfilLocal($this->normalizePersonnelKeys($fromHttp));
            }

            return $this->mapRowVersSiPersonnel($fromHttp, $matricule);
        }

        if (! $this->isDirectOracleOperational()) {
            return null;
        }

        $sql = trim((string) config('sig_oracle_report_groupe.lookup_personnel_sql'));
        if ($sql === '') {
            return null;
        }

        try {
            $row = DB::connection('oracle_report_groupe')->selectOne($sql, ['matricule' => $matricule]);
        } catch (Throwable $e) {
            Log::warning('SigOracleReportGroupe: échec lookup personnel (PDO)', [
                'matricule' => $matricule,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($row === null) {
            return null;
        }

        return $this->mapRowVersSiPersonnel((array) $row, $matricule);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function personnesLieesPourStaffMatricule(string $matricule): array
    {
        $matricule = trim($matricule);
        if ($matricule === '') {
            return [];
        }

        if ($this->httpClient->isConfigured()) {
            $rows = $this->httpClient->personnesLieesPourStaff($matricule);

            return array_map(fn (array $r) => $this->normalizeLieeRow($r), $rows);
        }

        if (! $this->isDirectOracleOperational()) {
            return [];
        }

        $sql = trim((string) config('sig_oracle_report_groupe.staff_liees_sql'));
        if ($sql === '') {
            return [];
        }

        try {
            $rows = DB::connection('oracle_report_groupe')->select($sql, ['matricule' => $matricule]);
        } catch (Throwable $e) {
            Log::warning('SigOracleReportGroupe: échec liste personnes liées (PDO)', [
                'matricule' => $matricule,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $o = (array) $row;
            $num = $this->string($o, ['numero_client', 'matricule', 'numero']);
            if ($num === null || $num === '') {
                continue;
            }
            $morale = $this->intBool($o['est_personne_morale'] ?? $o['personne_morale'] ?? $o['pm'] ?? 0);
            $out[] = [
                'numero_client' => $num,
                'prenom' => $this->string($o, ['prenom', 'prenom_client']),
                'nom' => $this->string($o, ['nom', 'nom_client']),
                'raison_sociale' => $this->string($o, ['raison_sociale', 'rs', 'denomination']),
                'est_personne_morale' => $morale,
                'type_relation' => $this->string($o, ['type_relation', 'relation', 'lib_relation']) ?? '—',
                'classe' => isset($o['classe']) ? (int) $o['classe'] : null,
            ];
        }

        return $out;
    }

    /**
     * @deprecated Utiliser usesHttpProxy() ou la logique interne ; conservé pour compatibilité éventuelle.
     */
    public function isOperational(): bool
    {
        return $this->httpClient->isConfigured() || $this->isDirectOracleOperational();
    }

    private function isDirectOracleOperational(): bool
    {
        if (! (bool) config('sig_oracle_report_groupe.enabled')) {
            return false;
        }
        if (! extension_loaded('oci8')) {
            return false;
        }
        $host = trim((string) config('database.connections.oracle_report_groupe.host'));
        $tns = trim((string) config('database.connections.oracle_report_groupe.tns'));

        return $host !== '' || $tns !== '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePersonnelKeys(array $data): array
    {
        if (! isset($data['matricule']) && isset($data['numero_client'])) {
            $data['matricule'] = $data['numero_client'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enrichPersonnelAvecProfilLocal(array $data): array
    {
        if (($data['type_client'] ?? 'personnel') !== 'personnel') {
            return $data;
        }
        if (array_key_exists('profile_id', $data) && $data['profile_id'] !== null && $data['profile_id'] !== '') {
            return $data;
        }

        $m = (string) ($data['matricule'] ?? '');
        $profil = $m !== '' ? Profil::query()->where('matricule', $m)->first() : null;
        $data['profile_id'] = $profil?->id;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function normalizeLieeRow(array $r): array
    {
        if (! isset($r['numero_client']) && isset($r['matricule'])) {
            $r['numero_client'] = $r['matricule'];
        }

        $r['est_personne_morale'] = $this->intBool($r['est_personne_morale'] ?? false);

        return $r;
    }

    /**
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>|null
     */
    private function mapRowVersSiPersonnel(array $o, string $matriculeFallback): ?array
    {
        $cle = $this->string($o, ['matricule', 'numero_client', 'num_client', 'matricule_rh', 'customer_no', 'sc_customer_no']);
        if ($cle === null || $cle === '') {
            $cle = $matriculeFallback;
        }

        $typeCode = trim((string) ($this->string($o, ['type_client', 'type']) ?? ''));
        $customerType = strtoupper((string) ($this->string($o, ['customer_type', 'sc_customer_type']) ?? ''));
        if ($typeCode === '2' || $customerType === 'C') {
            $typeClient = 'entreprise';
        } elseif ($typeCode === '1' || $customerType === 'I' || $typeCode === '') {
            $typeClient = 'personnel';
        } else {
            $typeRaw = strtolower($typeCode);
            $typeClient = (str_contains($typeRaw, 'entrepr') || str_contains($typeRaw, 'moral')) ? 'entreprise' : 'personnel';
        }

        if ($typeClient === 'entreprise') {
            $rs = $this->string($o, ['raison_sociale', 'rs', 'denomination', 'nom_entreprise', 'nom', 'full_name']);

            $ent = [
                'matricule' => $cle,
                'type_client' => 'entreprise',
                'profile_id' => null,
                'prenom' => null,
                'nom' => null,
                'raison_sociale' => $rs,
                'prenom_nom' => $rs ?? $cle,
                'adresse' => $this->string($o, ['adresse', 'adresse_siege', 'd_address1']),
                'genre' => $this->string($o, ['genre', 'categorie']) ?? null,
                'telephone' => $this->string($o, ['telephone', 'tel', 'phone', 'mobile_number', 'mobile']),
                'email' => $this->string($o, ['email', 'mail', 'e_mail']),
                'piece_type' => $this->string($o, ['unique_id_name', 'UNIQUE_ID_NAME', 'piece_type', 'type_piece']) ?? 'RCCM',
                'piece_numero' => $this->string($o, ['unique_id_value', 'UNIQUE_ID_VALUE', 'piece_numero', 'numero_piece', 'rccm', 'p_national_id', 'passport_no']),
                'agence' => $this->string($o, ['agence', 'lib_agence', 'code_agence', 'branch_name', 'local_branch']),
                'fonction' => null,
                'departement' => $this->string($o, ['departement', 'dept', 'service', 'cust_cat_desc']),
            ];

            return $this->appendEncoursFromRow($o, $ent);
        }

        $profil = Profil::query()->where('matricule', $cle)->first();

        $prenom = $this->string($o, ['prenom', 'prénom', 'first_name']);
        $nom = $this->string($o, ['nom', 'nom_famille', 'nom_naissance', 'middle_name']);
        $full = $this->string($o, ['full_name']);
        $prenomNom = trim(implode(' ', array_filter([$prenom, $nom]))) ?: ($full ?? $cle);

        $out = [
            'matricule' => $cle,
            'type_client' => 'personnel',
            'profile_id' => $profil?->id,
            'prenom' => $prenom,
            'nom' => $nom,
            'prenom_nom' => $prenomNom,
            'adresse' => $this->string($o, ['adresse', 'adresse_postale', 'd_address1']),
            'genre' => $this->string($o, ['genre', 'sexe', 'civilite', 'categorie']),
            'telephone' => $this->string($o, ['telephone', 'tel', 'phone', 'gsm', 'mobile', 'mobile_number']),
            'email' => $this->string($o, ['email', 'mail', 'adresse_mail', 'e_mail']),
            // FCUBS : UNIQUE_ID_NAME = type de pièce, UNIQUE_ID_VALUE = n° de pièce (STTM_CUSTOMER)
            'piece_type' => $this->string($o, ['unique_id_name', 'UNIQUE_ID_NAME', 'piece_type', 'type_piece', 'lib_piece']) ?? 'CNI',
            'piece_numero' => $this->string($o, ['unique_id_value', 'UNIQUE_ID_VALUE', 'piece_numero', 'numero_piece', 'num_piece', 'cni', 'p_national_id', 'passport_no']),
            'agence' => $this->string($o, ['agence', 'lib_agence', 'code_agence', 'site', 'branch_name', 'local_branch']),
            'fonction' => $this->string($o, ['fonction', 'lib_fonction', 'intitule_poste', 'cust_cat', 'cust_cat_desc']),
            'departement' => $this->string($o, ['departement', 'dept', 'direction', 'service', 'language', 'country']),
        ];

        return $this->appendEncoursFromRow($o, $out);
    }

    /**
     * @param  array<string, mixed>  $o
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function appendEncoursFromRow(array $o, array $out): array
    {
        foreach (['encours_total', 'encours_balance', 'total_encours', 'encours', 'sum_encours'] as $k) {
            foreach ([$k, strtolower($k)] as $variant) {
                if (! array_key_exists($variant, $o) || $o[$variant] === null) {
                    continue;
                }
                $raw = $o[$variant];
                $out['encours_total'] = is_numeric($raw) ? (float) $raw : (float) str_replace(',', '.', (string) $raw);
                break 2;
            }
        }
        if (array_key_exists('value_date', $o) && $o['value_date'] !== null) {
            $out['value_date'] = (string) $o['value_date'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $o
     */
    private function string(array $o, array $keys): ?string
    {
        foreach ($keys as $k) {
            $variants = array_values(array_unique(array_filter(
                [$k, strtolower($k), strtoupper($k)],
                fn (string $x) => $x !== ''
            )));
            foreach ($variants as $variant) {
                if (! array_key_exists($variant, $o)) {
                    continue;
                }
                $v = $o[$variant];
                if ($v === null) {
                    continue;
                }
                $s = trim((string) $v);

                return $s === '' ? null : $s;
            }
        }

        return null;
    }

    private function intBool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            $l = strtolower(trim($v));

            return in_array($l, ['1', 'y', 'yes', 'o', 'oui', 'true', 't'], true);
        }

        return (int) $v !== 0;
    }
}
