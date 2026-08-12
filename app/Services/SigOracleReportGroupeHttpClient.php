<?php

namespace App\Services;

use App\Helpers\FilialeHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Appels HTTP vers le service Python (Oracle REPORT_GROUPE).
 */
class SigOracleReportGroupeHttpClient
{
    public function isConfigured(): bool
    {
        if (! (bool) config('sig_oracle_report_groupe.http.enabled')) {
            return false;
        }

        return trim((string) config('sig_oracle_report_groupe.http.base_url')) !== '';
    }

    /**
     * @return array<string, mixed>|null Même forme que le lookup personnel attendu par le front
     */
    public function lookupPersonnel(string $matricule): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $matricule = trim($matricule);
        if ($matricule === '') {
            return null;
        }

        $url = $this->url(config('sig_oracle_report_groupe.http.lookup_personnel_path'));
        $pays = FilialeHelper::oraclePaysPrefix();

        try {
            $request = Http::timeout((int) config('sig_oracle_report_groupe.http.timeout', 30))
                ->acceptJson()
                ->asJson()
                ->withHeaders($this->paysHeaders($pays));

            if (! config('sig_oracle_report_groupe.http.verify_ssl')) {
                $request = $request->withoutVerifying();
            }

            $token = trim((string) config('sig_oracle_report_groupe.http.token'));
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post($url, [
                'matricule' => $matricule,
                'pays' => $pays,
            ]);
        } catch (Throwable $e) {
            Log::warning('SigOracle HTTP: échec lookup personnel', [
                'matricule' => $matricule,
                'pays' => $pays,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('SigOracle HTTP: réponse HTTP erreur lookup personnel', [
                'matricule' => $matricule,
                'pays' => $pays,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $this->decodePersonnelPayload($response->json());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function personnesLieesPourStaff(string $matricule): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $matricule = trim($matricule);
        if ($matricule === '') {
            return [];
        }

        $pays = FilialeHelper::oraclePaysPrefix();
        $path = (string) config('sig_oracle_report_groupe.http.staff_liees_path');
        $path = str_replace('{matricule}', rawurlencode($matricule), $path);
        $url = $this->urlWithPays($path, $pays);

        try {
            $request = Http::timeout((int) config('sig_oracle_report_groupe.http.timeout', 30))
                ->acceptJson()
                ->withHeaders($this->paysHeaders($pays));

            if (! config('sig_oracle_report_groupe.http.verify_ssl')) {
                $request = $request->withoutVerifying();
            }

            $token = trim((string) config('sig_oracle_report_groupe.http.token'));
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
        } catch (Throwable $e) {
            Log::warning('SigOracle HTTP: échec personnes liées', [
                'matricule' => $matricule,
                'pays' => $pays,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->decodeLieesPayload($response->json());
    }

    /**
     * Détection globale staff ↔ clients (caution + cotitulaires).
     *
     * @return list<array<string, mixed>>
     */
    public function detectionStaffClients(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $pays = FilialeHelper::oraclePaysPrefix();
        $path = (string) config('sig_oracle_report_groupe.http.detection_staff_clients_path');
        $url = $this->urlWithPays($path, $pays);

        try {
            $request = Http::timeout(max(60, (int) config('sig_oracle_report_groupe.http.timeout', 30)))
                ->acceptJson()
                ->withHeaders($this->paysHeaders($pays));

            if (! config('sig_oracle_report_groupe.http.verify_ssl')) {
                $request = $request->withoutVerifying();
            }

            $token = trim((string) config('sig_oracle_report_groupe.http.token'));
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
        } catch (Throwable $e) {
            Log::warning('SigOracle HTTP: échec détection staff-clients', [
                'pays' => $pays,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('SigOracle HTTP: réponse erreur détection staff-clients', [
                'pays' => $pays,
                'status' => $response->status(),
            ]);

            return [];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return [];
        }
        $rows = $json['data'] ?? [];
        if (! is_array($rows) || ($rows !== [] && ! array_is_list($rows))) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Alertes doublons clients (STDCIF).
     *
     * @return list<array<string, mixed>>
     */
    public function alertesDoublonsClients(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $pays = FilialeHelper::oraclePaysPrefix();
        $path = (string) config('sig_oracle_report_groupe.http.alertes_doublons_clients_path');
        $url = $this->urlWithPays($path, $pays);

        try {
            $request = Http::timeout(max(180, (int) config('sig_oracle_report_groupe.http.timeout', 30)))
                ->acceptJson()
                ->withHeaders($this->paysHeaders($pays));

            if (! config('sig_oracle_report_groupe.http.verify_ssl')) {
                $request = $request->withoutVerifying();
            }

            $token = trim((string) config('sig_oracle_report_groupe.http.token'));
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
        } catch (Throwable $e) {
            Log::warning('SigOracle HTTP: échec alertes doublons clients', [
                'pays' => $pays,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('SigOracle HTTP: réponse erreur alertes doublons clients', [
                'pays' => $pays,
                'status' => $response->status(),
            ]);

            return [];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return [];
        }
        $rows = $json['data'] ?? [];
        if (! is_array($rows) || ($rows !== [] && ! array_is_list($rows))) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function ping(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $base = rtrim((string) config('sig_oracle_report_groupe.http.base_url'), '/');

        try {
            $request = Http::timeout(10)->acceptJson();
            if (! config('sig_oracle_report_groupe.http.verify_ssl')) {
                $request = $request->withoutVerifying();
            }
            $token = trim((string) config('sig_oracle_report_groupe.http.token'));
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $r = $request->get($base.'/health');

            return $r->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function paysHeaders(string $pays): array
    {
        return [
            'X-Oracle-Pays' => $pays,
            'X-Pays' => $pays,
        ];
    }

    private function url(string $path): string
    {
        $base = rtrim((string) config('sig_oracle_report_groupe.http.base_url'), '/');
        $path = $path[0] === '/' ? $path : '/'.$path;

        return $base.$path;
    }

    private function urlWithPays(string $path, string $pays): string
    {
        $url = $this->url($path);
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.'pays='.rawurlencode($pays);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>|null
     */
    private function decodePersonnelPayload(?array $json): ?array
    {
        if ($json === null) {
            return null;
        }

        if (array_key_exists('ok', $json) && $json['ok'] === false) {
            return null;
        }

        $data = $json['data'] ?? $json;
        if (! is_array($data)) {
            return null;
        }

        // FCUBS / KYC : CUSTOMER_NO → customer_no côté Python ; aligner sur matricule
        if (! isset($data['matricule']) || trim((string) $data['matricule']) === '') {
            foreach (['customer_no', 'sc_customer_no', 'numero_client', 'matricule_client'] as $k) {
                if (! empty($data[$k])) {
                    $data['matricule'] = trim((string) $data[$k]);
                    break;
                }
            }
        }

        if (! isset($data['matricule']) || $data['matricule'] === '') {
            return null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $json
     * @return list<array<string, mixed>>
     */
    private function decodeLieesPayload(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $rows = $json['data'] ?? $json;
        if (! is_array($rows)) {
            return [];
        }

        if ($rows !== [] && ! array_is_list($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! isset($row['numero_client']) && ! isset($row['matricule'])) {
                foreach (['cust_ac_no', 'customer_no', 'numero_client_si'] as $k) {
                    if (! empty($row[$k])) {
                        $row['numero_client'] = trim((string) $row[$k]);
                        break;
                    }
                }
            }
            if (isset($row['numero_client']) || isset($row['matricule'])) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
