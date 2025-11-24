<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Profil;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUserWithProfil extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-with-profil 
                            {--name= : Nom complet de l\'utilisateur}
                            {--email= : Email de l\'utilisateur}
                            {--password= : Mot de passe}
                            {--profil-matricule= : Matricule du profil}
                            {--profil-prenom= : Prénom du profil}
                            {--profil-nom= : Nom du profil}
                            {--roles= : Rôles séparés par des virgules (admin,metier,controle)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Créer un utilisateur avec un profil et des rôles associés';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Collecter les informations
        $name = $this->option('name') ?? $this->ask('Nom complet de l\'utilisateur');
        $email = $this->option('email') ?? $this->ask('Email (doit correspondre au profil)');
        $password = $this->option('password') ?? $this->secret('Mot de passe');

        $matricule = $this->option('profil-matricule') ?? $this->ask('Matricule du profil');
        $prenom = $this->option('profil-prenom') ?? $this->ask('Prénom du profil');
        $nom = $this->option('profil-nom') ?? $this->ask('Nom du profil');

        // Validation
        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
            'matricule' => $matricule,
            'prenom' => $prenom,
            'nom' => $nom,
        ], [
            'email' => 'required|email|unique:users,email|unique:profiles,email',
            'password' => 'required|min:8',
            'matricule' => 'required|string|unique:profiles,matricule',
            'prenom' => 'required|string',
            'nom' => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->error('Erreurs de validation :');
            foreach ($validator->errors()->all() as $error) {
                $this->error('  - ' . $error);
            }
            return 1;
        }

        try {
            \DB::beginTransaction();

            // Créer l'utilisateur
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $this->info("✓ Utilisateur créé : {$user->email}");

            // Créer le profil
            $profil = Profil::create([
                'matricule' => $matricule,
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email, // Même email que l'utilisateur
                'statut' => 'actif',
            ]);

            $this->info("✓ Profil créé : {$profil->prenom} {$profil->nom} ({$profil->matricule})");

            // Assigner les rôles
            $rolesInput = $this->option('roles');
            if (!$rolesInput) {
                $this->info("\nRôles disponibles : admin, metier, controle");
                $rolesInput = $this->ask('Rôles (séparés par des virgules, ou vide pour aucun)');
            }

            if ($rolesInput) {
                $roleSlugs = array_map('trim', explode(',', $rolesInput));
                $roles = Role::whereIn('slug', $roleSlugs)->get();

                if ($roles->isEmpty()) {
                    $this->warn("Aucun rôle trouvé avec les slugs : " . implode(', ', $roleSlugs));
                } else {
                    $profil->roles()->sync($roles->pluck('id'));
                    $this->info("✓ Rôles assignés : " . $roles->pluck('nom')->implode(', '));
                }
            }

            \DB::commit();

            $this->info("\n✅ Utilisateur créé avec succès !");
            $this->table(
                ['Type', 'Valeur'],
                [
                    ['Email', $user->email],
                    ['Nom', $user->name],
                    ['Profil', "{$profil->prenom} {$profil->nom}"],
                    ['Matricule', $profil->matricule],
                    ['Rôles', $profil->roles->pluck('nom')->implode(', ') ?: 'Aucun'],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            \DB::rollBack();
            $this->error("Erreur lors de la création : " . $e->getMessage());
            return 1;
        }
    }
}

