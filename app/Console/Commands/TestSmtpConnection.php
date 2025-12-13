<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestSmtpConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {email? : L\'adresse email de destination pour le test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tester la connexion SMTP et envoyer un email de test';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('  TEST DE CONFIGURATION SMTP');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        // Afficher la configuration actuelle
        $this->displayConfiguration();

        $this->newLine();
        $this->info('Test de connexion SMTP...');
        $this->newLine();

        // Tester la connexion
        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $username = config('mail.mailers.smtp.username');
        $encryption = config('mail.mailers.smtp.encryption');

        if ($mailer === 'log') {
            $this->warn('⚠ Mode LOG activé - Les emails seront loggés, pas envoyés');
            $this->newLine();
            $this->info('Pour tester l\'envoi réel, configurez MAIL_MAILER=smtp dans .env');
            return 0;
        }

        if ($mailer !== 'smtp') {
            $this->error("❌ Le mailer configuré est '{$mailer}', pas 'smtp'");
            $this->info('Configurez MAIL_MAILER=smtp dans votre fichier .env');
            return 1;
        }

        // Vérifier que les paramètres sont configurés
        if (empty($host) || empty($username)) {
            $this->error('❌ Configuration SMTP incomplète');
            $this->info('Vérifiez que MAIL_HOST et MAIL_USERNAME sont définis dans .env');
            return 1;
        }

        $this->info("✓ Mailer: {$mailer}");
        $this->info("✓ Host: {$host}");
        $this->info("✓ Port: {$port}");
        $this->info("✓ Encryption: {$encryption}");
        $this->info("✓ Username: {$username}");
        $this->info("✓ Password: " . (config('mail.mailers.smtp.password') ? '***configuré***' : '❌ NON CONFIGURÉ'));
        $this->newLine();

        // Demander l'email de destination si non fourni
        $testEmail = $this->argument('email');
        if (!$testEmail) {
            $testEmail = $this->ask('Entrez l\'adresse email de destination pour le test');
        }

        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error("❌ Adresse email invalide: {$testEmail}");
            return 1;
        }

        $this->info("Envoi d'un email de test à: {$testEmail}");
        $this->newLine();

        try {
            Mail::raw('Ceci est un email de test depuis votre application COFINA. Si vous recevez ce message, votre configuration SMTP fonctionne correctement!', function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Test SMTP - Application COFINA');
            });

            $this->newLine();
            $this->info('═══════════════════════════════════════════════════════════════');
            $this->info('  ✅ EMAIL ENVOYÉ AVEC SUCCÈS !');
            $this->info('═══════════════════════════════════════════════════════════════');
            $this->newLine();
            $this->info("Vérifiez votre boîte de réception ({$testEmail}) pour confirmer la réception.");
            $this->newLine();

            return 0;
        } catch (\Exception $e) {
            $this->newLine();
            $errorMessage = $e->getMessage();
            
            // Vérifier si c'est une erreur d'authentification
            if (str_contains($errorMessage, 'Authentication unsuccessful') || 
                str_contains($errorMessage, '535') ||
                str_contains($errorMessage, 'Failed to authenticate')) {
                
                $this->error('═══════════════════════════════════════════════════════════════');
                $this->error('  ❌ ERREUR D\'AUTHENTIFICATION SMTP');
                $this->error('═══════════════════════════════════════════════════════════════');
                $this->newLine();
                $this->error('Message d\'erreur:');
                $this->line($errorMessage);
                $this->newLine();
                
                $this->warn('🔍 PROBLÈME D\'AUTHENTIFICATION DÉTECTÉ');
                $this->newLine();
                $this->info('Solutions possibles:');
                $this->line('1. Vérifiez que MAIL_PASSWORD est correct dans .env');
                $this->line('2. Si 2FA est activé, utilisez un "Mot de passe d\'application"');
                $this->line('3. Vérifiez que SMTP AUTH est activé pour ce compte dans Office 365');
                $this->line('4. Contactez votre administrateur IT si le problème persiste');
            } elseif (str_contains($errorMessage, 'Connection') || str_contains($errorMessage, 'timeout')) {
                $this->error('═══════════════════════════════════════════════════════════════');
                $this->error('  ❌ ERREUR DE CONNEXION SMTP');
                $this->error('═══════════════════════════════════════════════════════════════');
                $this->newLine();
                $this->error('Message d\'erreur:');
                $this->line($errorMessage);
                $this->newLine();
                $this->warn('🔍 PROBLÈME DE CONNEXION DÉTECTÉ');
                $this->newLine();
                $this->info('Solutions possibles:');
                $this->line('1. Vérifiez votre connexion Internet');
                $this->line('2. Vérifiez que MAIL_HOST=smtp.office365.com');
                $this->line('3. Vérifiez que le port 587 n\'est pas bloqué par un firewall');
            } else {
                $this->error('═══════════════════════════════════════════════════════════════');
                $this->error('  ❌ ERREUR INATTENDUE');
                $this->error('═══════════════════════════════════════════════════════════════');
                $this->newLine();
                $this->error('Message: ' . $errorMessage);
            }
            
            $this->newLine();
            return 1;
        }
    }

    /**
     * Afficher la configuration actuelle
     */
    private function displayConfiguration()
    {
        $this->info('Configuration actuelle:');
        $this->line('─────────────────────────────────────────────────────────────');
        $this->line('MAIL_MAILER: ' . config('mail.default'));
        $this->line('MAIL_HOST: ' . (config('mail.mailers.smtp.host') ?: '❌ Non configuré'));
        $this->line('MAIL_PORT: ' . (config('mail.mailers.smtp.port') ?: '❌ Non configuré'));
        $this->line('MAIL_ENCRYPTION: ' . (config('mail.mailers.smtp.encryption') ?: '❌ Non configuré'));
        $this->line('MAIL_USERNAME: ' . (config('mail.mailers.smtp.username') ?: '❌ Non configuré'));
        $this->line('MAIL_PASSWORD: ' . (config('mail.mailers.smtp.password') ? '***configuré***' : '❌ Non configuré'));
        $this->line('MAIL_FROM_ADDRESS: ' . config('mail.from.address'));
        $this->line('MAIL_FROM_NAME: ' . config('mail.from.name'));
        $this->line('─────────────────────────────────────────────────────────────');
    }
}
