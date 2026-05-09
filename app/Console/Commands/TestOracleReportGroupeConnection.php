<?php

namespace App\Console\Commands;

use App\Services\SigOracleReportGroupeHttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TestOracleReportGroupeConnection extends Command
{
    protected $signature = 'sig:test-oracle-report-groupe';

    protected $description = 'Tester REPORT_GROUPE : proxy HTTP (Python) ou Oracle direct (oci8 + DUAL)';

    public function handle(SigOracleReportGroupeHttpClient $httpClient): int
    {
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('  TEST — Oracle REPORT_GROUPE');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        if ($httpClient->isConfigured()) {
            $this->info('Mode : proxy HTTP (SIG_ORACLE_HTTP_*) — oci8 PHP non requis.');
            $this->line('Base URL : '.config('sig_oracle_report_groupe.http.base_url'));
            $this->newLine();

            if ($httpClient->ping()) {
                $this->info('GET /health : OK (service Python joignable).');

                return self::SUCCESS;
            }

            $this->error('Le service Python ne répond pas sur GET {base}/health.');
            $this->line('Démarrez le proxy : cd scripts/oracle-proxy-python && uvicorn app:app --host 127.0.0.1 --port 8810');

            return self::FAILURE;
        }

        if (! extension_loaded('oci8')) {
            $this->error('Ni proxy HTTP (SIG_ORACLE_HTTP_ENABLED + SIG_ORACLE_HTTP_BASE_URL), ni extension oci8.');
            $this->newLine();
            $this->warn('Option A — Python : dans .env, SIG_ORACLE_HTTP_ENABLED=true et SIG_ORACLE_HTTP_BASE_URL=http://127.0.0.1:8810');
            $this->warn('Option B — PHP : installez oci8 puis ORACLE_REPORT_GROUPE_* + requêtes SQL.');

            return self::FAILURE;
        }

        $this->info('Mode : Oracle direct (oci8).');
        $cfg = config('database.connections.oracle_report_groupe');
        if (! is_array($cfg) || ($cfg['driver'] ?? '') !== 'oracle') {
            $this->error('Connexion « oracle_report_groupe » absente dans config/database.php.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Host / service : '.($cfg['host'] ?? '').' / '.($cfg['service_name'] ?? ''));

        try {
            $rows = DB::connection('oracle_report_groupe')->select('SELECT 1 AS connectivity_test FROM DUAL');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === [] || ! isset($rows[0])) {
            $this->error('Réponse vide.');

            return self::FAILURE;
        }

        $row = (array) $rows[0];
        $val = $row['connectivity_test'] ?? $row['CONNECTIVITY_TEST'] ?? reset($row);
        $this->info('DUAL : '.json_encode($val));

        return self::SUCCESS;
    }
}
