<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AvanceSalaireIntegrationExterneService
{
    public function estActive(): bool
    {
        return config('avance_salaire.integrations.mode') === 'external';
    }

    /**
     * Envoie le dossier d’intégration vers l’application tierce (paie / opérations / core).
     * Contrat attendu : POST JSON, Bearer token, réponse 2xx.
     *
     * Corps typique : meta, effectue_par, type_envoi (optionnel : dossier ou synthese_template_integration_rh),
     * avance_salaire_demande_id / demande / integration pour un envoi par dossier,
     * template_comptable (colonnes + lignes alignées écran « Template comptable »),
     * lignes (format technique interne, compatibilité ; peut inclure avance_salaire_demande_id en envoi agrégé).
     *
     * @param  array<string, mixed>  $payload
     */
    public function envoyer(array $payload): void
    {
        $base = rtrim((string) config('avance_salaire.integrations.external.base_url'), '/');
        $path = (string) config('avance_salaire.integrations.external.path', '/api/integrations/avances-salaire');
        $path = str_starts_with($path, '/') ? $path : '/'.$path;
        $url = $base !== '' ? $base.$path : '';
        $token = trim((string) config('avance_salaire.integrations.external.token', ''));

        if ($url === '' || $token === '') {
            throw new RuntimeException('Intégration externe : AVANCE_SALAIRE_INTEGRATION_EXTERNAL_BASE_URL ou TOKEN manquant.');
        }

        $timeout = (int) config('avance_salaire.integrations.external.timeout', 30);
        $verify = (bool) config('avance_salaire.integrations.external.verify_ssl', true);

        $response = Http::withToken($token)
            ->timeout($timeout)
            ->withOptions(['verify' => $verify])
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $body = $response->body();
            $snippet = strlen($body) > 500 ? substr($body, 0, 500).'…' : $body;
            throw new RuntimeException(
                'L’application d’intégration a répondu avec une erreur (HTTP '.$response->status().'). '.$snippet
            );
        }
    }
}
