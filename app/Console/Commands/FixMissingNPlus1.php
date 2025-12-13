<?php

namespace App\Console\Commands;

use App\Models\Profil;
use App\Models\Departement;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class FixMissingNPlus1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'profils:fix-n-plus1 {--file=} {--use-department-manager} {--use-existing-data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige les N+1 manquants des profils. Peut utiliser un fichier Excel, le responsable du département, ou les données existantes dans la base.';

    /**
     * Fonction pour normaliser les accents
     */
    private function normalizeAccents($str)
    {
        $str = strtolower($str);
        $str = str_replace(
            ['à', 'á', 'â', 'ã', 'ä', 'å', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'ç', 'ñ'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'c', 'n'],
            $str
        );
        return $str;
    }

    /**
     * Recherche un profil par nom (insensible aux accents)
     */
    private function findProfilByName($name)
    {
        if (empty($name)) {
            return null;
        }

        $nameNormalized = preg_replace('/\s+/', ' ', trim($name));
        $nameLower = $this->normalizeAccents($nameNormalized);
        
        $nameParts = preg_split('/\s+/', trim($nameNormalized));
        
        // Essayer d'abord par matricule
        $profil = Profil::where('matricule', $name)->first();
        if ($profil) {
            return $profil;
        }
        
        // Essayer par email
        $profil = Profil::where('email', $name)->first();
        if ($profil) {
            return $profil;
        }
        
        // Récupérer tous les profils et comparer en PHP (pour gérer les accents)
        $allProfils = Profil::select('id', 'nom', 'prenom', 'matricule')->get();
        
        if (count($nameParts) >= 2) {
            $prenom = trim($nameParts[0]);
            $nom = trim($nameParts[count($nameParts) - 1]);
            
            // Essayer "Prénom Nom"
            foreach ($allProfils as $profilCandidate) {
                $prenomNormalized = $this->normalizeAccents($profilCandidate->prenom);
                $nomNormalized = $this->normalizeAccents($profilCandidate->nom);
                
                if ($prenomNormalized === $this->normalizeAccents($prenom) && 
                    $nomNormalized === $this->normalizeAccents($nom)) {
                    return $profilCandidate;
                }
            }
            
            // Essayer "Nom Prénom"
            if (count($nameParts) == 2) {
                foreach ($allProfils as $profilCandidate) {
                    $prenomNormalized = $this->normalizeAccents($profilCandidate->prenom);
                    $nomNormalized = $this->normalizeAccents($profilCandidate->nom);
                    
                    if ($nomNormalized === $this->normalizeAccents($nameParts[0]) && 
                        $prenomNormalized === $this->normalizeAccents($nameParts[1])) {
                        return $profilCandidate;
                    }
                }
            }
        }
        
        // Recherche partielle sur le nom complet
        foreach ($allProfils as $profilCandidate) {
            $fullNameCandidate = $this->normalizeAccents(trim($profilCandidate->prenom . ' ' . $profilCandidate->nom));
            $fullNameCandidateReverse = $this->normalizeAccents(trim($profilCandidate->nom . ' ' . $profilCandidate->prenom));
            
            if ($fullNameCandidate === $nameLower || 
                $fullNameCandidateReverse === $nameLower) {
                return $profilCandidate;
            }
            
            // Correspondance partielle
            if (count($nameParts) >= 2) {
                $firstWord = $nameParts[0];
                $lastWord = $nameParts[count($nameParts) - 1];
                
                $prenomNormalized = $this->normalizeAccents($profilCandidate->prenom);
                $nomNormalized = $this->normalizeAccents($profilCandidate->nom);
                
                if ((strpos($fullNameCandidate, $this->normalizeAccents($firstWord)) === 0 || 
                     strpos($prenomNormalized, $this->normalizeAccents($firstWord)) === 0) &&
                    (strpos($fullNameCandidate, $this->normalizeAccents($lastWord)) !== false || 
                     strpos($nomNormalized, $this->normalizeAccents($lastWord)) === 0)) {
                    return $profilCandidate;
                }
            }
        }
        
        return null;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Correction des N+1 manquants...');
        $this->newLine();
        
        $file = $this->option('file');
        $useDepartmentManager = $this->option('use-department-manager');
        $useExistingData = $this->option('use-existing-data');
        
        $profilsSansN1 = Profil::whereNull('n_plus_1_id')->get();
        $this->info("Nombre de profils sans N+1: " . $profilsSansN1->count());
        $this->newLine();
        
        $fixed = 0;
        $notFound = 0;
        $errors = [];
        
        if ($file && file_exists($file)) {
            // Utiliser un fichier Excel
            $this->info("Utilisation du fichier Excel: $file");
            $this->newLine();
            
            try {
                $data = Excel::toArray([], $file);
                if (empty($data) || empty($data[0])) {
                    $this->error('Le fichier Excel est vide.');
                    return Command::FAILURE;
                }
                
                $rows = $data[0];
                $header = array_shift($rows);
                
                // Normaliser les en-têtes
                $headerMap = [];
                foreach ($header as $index => $col) {
                    $normalized = strtolower(trim($col));
                    $headerMap[$normalized] = $index;
                }
                
                // Mapping des colonnes
                $matriculeIndex = null;
                $nomIndex = null;
                $prenomIndex = null;
                $nPlus1Index = null;
                
                foreach ($headerMap as $normalized => $index) {
                    if (in_array($normalized, ['matricule', 'mat', 'id', 'employee_id'])) {
                        $matriculeIndex = $index;
                    }
                    if (in_array($normalized, ['nom', 'name', 'lastname', 'last_name'])) {
                        $nomIndex = $index;
                    }
                    if (in_array($normalized, ['prenom', 'firstname', 'first_name', 'prénom'])) {
                        $prenomIndex = $index;
                    }
                    if (in_array($normalized, ['n+1', 'n_plus_1', 'n plus 1', 'superieur', 'superieur hierarchique', 'superieur_hierarchique', 'manager', 'responsable'])) {
                        $nPlus1Index = $index;
                    }
                }
                
                if ($nPlus1Index === null) {
                    $this->error('Colonne N+1 non trouvée dans le fichier Excel.');
                    return Command::FAILURE;
                }
                
                // Récupérer tous les profils pour la recherche (pas seulement ceux sans N+1, car on peut vouloir corriger aussi)
                $allProfils = Profil::all();
                
                // Créer un mapping des profils par matricule ou nom/prénom (insensible à la casse et aux accents)
                $profilsMap = [];
                foreach ($allProfils as $profil) {
                    // Par matricule
                    if (!empty($profil->matricule)) {
                        $key = strtolower(trim($profil->matricule));
                        if (!isset($profilsMap[$key])) {
                            $profilsMap[$key] = $profil;
                        }
                    }
                    
                    // Par nom/prénom (normalisé)
                    $nameKey = $this->normalizeAccents(trim($profil->prenom . ' ' . $profil->nom));
                    if (!isset($profilsMap[$nameKey])) {
                        $profilsMap[$nameKey] = $profil;
                    }
                    
                    // Par nom/prénom inversé
                    $nameKeyReverse = $this->normalizeAccents(trim($profil->nom . ' ' . $profil->prenom));
                    if (!isset($profilsMap[$nameKeyReverse])) {
                        $profilsMap[$nameKeyReverse] = $profil;
                    }
                    
                    // Par email
                    if (!empty($profil->email)) {
                        $emailKey = strtolower(trim($profil->email));
                        if (!isset($profilsMap[$emailKey])) {
                            $profilsMap[$emailKey] = $profil;
                        }
                    }
                }
                
                $this->info("Traitement de " . count($rows) . " lignes du fichier Excel...");
                $this->newLine();
                
                // Parcourir les lignes du fichier Excel
                foreach ($rows as $rowIndex => $row) {
                    if (empty(array_filter($row))) {
                        continue;
                    }
                    
                    $nPlus1Value = isset($row[$nPlus1Index]) ? trim($row[$nPlus1Index] ?? '') : '';
                    if (empty($nPlus1Value)) {
                        continue;
                    }
                    
                    // Trouver le profil correspondant dans la ligne
                    $profil = null;
                    
                    // Essayer par matricule
                    if ($matriculeIndex !== null && isset($row[$matriculeIndex])) {
                        $matricule = trim($row[$matriculeIndex] ?? '');
                        if (!empty($matricule)) {
                            $key = strtolower(trim($matricule));
                            if (isset($profilsMap[$key])) {
                                $profil = $profilsMap[$key];
                            }
                        }
                    }
                    
                    // Essayer par nom/prénom
                    if (!$profil && $nomIndex !== null && $prenomIndex !== null) {
                        $nom = trim($row[$nomIndex] ?? '');
                        $prenom = trim($row[$prenomIndex] ?? '');
                        if (!empty($nom) && !empty($prenom)) {
                            $nameKey = $this->normalizeAccents(trim($prenom . ' ' . $nom));
                            if (isset($profilsMap[$nameKey])) {
                                $profil = $profilsMap[$nameKey];
                            } else {
                                $nameKeyReverse = $this->normalizeAccents(trim($nom . ' ' . $prenom));
                                if (isset($profilsMap[$nameKeyReverse])) {
                                    $profil = $profilsMap[$nameKeyReverse];
                                }
                            }
                        }
                    }
                    
                    // Si le profil n'a pas de N+1 ou si on veut forcer la mise à jour
                    if ($profil) {
                        // Chercher le N+1
                        $nPlus1 = $this->findProfilByName($nPlus1Value);
                        
                        if ($nPlus1 && $nPlus1->id != $profil->id) {
                            $oldN1 = $profil->n_plus_1_id;
                            $profil->n_plus_1_id = $nPlus1->id;
                            
                            // Calculer N+2
                            if ($nPlus1->n_plus_1_id && $nPlus1->n_plus_1_id != $profil->id) {
                                $profil->n_plus_2_id = $nPlus1->n_plus_1_id;
                            }
                            
                            $profil->save();
                            $fixed++;
                            
                            if ($oldN1) {
                                $this->info("✓ {$profil->prenom} {$profil->nom} -> N+1 mis à jour: {$nPlus1->prenom} {$nPlus1->nom}");
                            } else {
                                $this->info("✓ {$profil->prenom} {$profil->nom} -> N+1 ajouté: {$nPlus1->prenom} {$nPlus1->nom}");
                            }
                        } elseif (!$nPlus1) {
                            $notFound++;
                            $profilName = $profil->prenom . ' ' . $profil->nom;
                            $errors[] = "Ligne " . ($rowIndex + 2) . ": N+1 non trouvé pour $profilName (N+1 recherché: $nPlus1Value)";
                        }
                    } else {
                        // Profil non trouvé dans la base de données
                        $nom = $nomIndex !== null ? trim($row[$nomIndex] ?? '') : '';
                        $prenom = $prenomIndex !== null ? trim($row[$prenomIndex] ?? '') : '';
                        $matricule = $matriculeIndex !== null ? trim($row[$matriculeIndex] ?? '') : '';
                        $errors[] = "Ligne " . ($rowIndex + 2) . ": Profil non trouvé (Matricule: $matricule, Nom: $nom, Prénom: $prenom)";
                    }
                }
            } catch (\Exception $e) {
                $this->error('Erreur lors de la lecture du fichier Excel: ' . $e->getMessage());
                return Command::FAILURE;
            }
        } elseif ($useExistingData) {
            // Utiliser les données existantes dans la base pour trouver les N+1
            $this->info("Utilisation des données existantes dans la base...");
            $this->newLine();
            
            // Récupérer tous les profils avec leurs N+1 pour créer un mapping
            $allProfils = Profil::with('nPlus1')->get();
            $profilsAvecN1 = $allProfils->filter(fn($p) => $p->n_plus_1_id !== null);
            
            $this->info("Profils avec N+1 dans la base: " . $profilsAvecN1->count());
            $this->newLine();
            
            // Créer un mapping par département : si plusieurs profils du même département ont le même N+1,
            // on peut l'assigner aux autres profils du même département sans N+1
            $departementN1Map = [];
            foreach ($profilsAvecN1 as $profil) {
                if ($profil->departement && $profil->n_plus_1_id) {
                    $dept = $profil->departement;
                    if (!isset($departementN1Map[$dept])) {
                        $departementN1Map[$dept] = [];
                    }
                    $departementN1Map[$dept][$profil->n_plus_1_id] = ($departementN1Map[$dept][$profil->n_plus_1_id] ?? 0) + 1;
                }
            }
            
            // Pour chaque département, trouver le N+1 le plus fréquent
            $departementN1Frequent = [];
            foreach ($departementN1Map as $dept => $n1Counts) {
                if (!empty($n1Counts)) {
                    arsort($n1Counts);
                    $mostFrequentN1Id = array_key_first($n1Counts);
                    $departementN1Frequent[$dept] = $mostFrequentN1Id;
                }
            }
            
            // Assigner le N+1 le plus fréquent du département aux profils sans N+1
            foreach ($profilsSansN1 as $profil) {
                if ($profil->departement && isset($departementN1Frequent[$profil->departement])) {
                    $n1Id = $departementN1Frequent[$profil->departement];
                    $n1 = Profil::find($n1Id);
                    
                    if ($n1 && $n1->id != $profil->id) {
                        $profil->n_plus_1_id = $n1->id;
                        
                        // Calculer N+2
                        if ($n1->n_plus_1_id && $n1->n_plus_1_id != $profil->id) {
                            $profil->n_plus_2_id = $n1->n_plus_1_id;
                        }
                        
                        $profil->save();
                        $fixed++;
                        $this->info("✓ {$profil->prenom} {$profil->nom} -> N+1: {$n1->prenom} {$n1->nom} (Département: {$profil->departement})");
                    }
                }
            }
            
            // Essayer aussi de trouver les N+1 par similarité de nom dans le même département
            $this->newLine();
            $this->info("Recherche par similarité dans le même département...");
            
            foreach ($profilsSansN1 as $profil) {
                if ($profil->departement && !$profil->n_plus_1_id) {
                    // Chercher dans le même département les profils qui ont un N+1
                    $profilsMemeDept = Profil::where('departement', $profil->departement)
                        ->where('id', '!=', $profil->id)
                        ->whereNotNull('n_plus_1_id')
                        ->get();
                    
                    if ($profilsMemeDept->isNotEmpty()) {
                        // Prendre le N+1 du premier profil du même département qui en a un
                        $profilReference = $profilsMemeDept->first();
                        $n1 = $profilReference->nPlus1;
                        
                        if ($n1 && $n1->id != $profil->id) {
                            $profil->n_plus_1_id = $n1->id;
                            
                            // Calculer N+2
                            if ($n1->n_plus_1_id && $n1->n_plus_1_id != $profil->id) {
                                $profil->n_plus_2_id = $n1->n_plus_1_id;
                            }
                            
                            $profil->save();
                            $fixed++;
                            $this->info("✓ {$profil->prenom} {$profil->nom} -> N+1: {$n1->prenom} {$n1->nom} (par similarité département)");
                        }
                    }
                }
            }
            
        } elseif ($useDepartmentManager) {
            // Utiliser le responsable du département
            $this->info("Utilisation du responsable du département comme N+1...");
            $this->newLine();
            
            foreach ($profilsSansN1 as $profil) {
                if (!$profil->departement) {
                    continue;
                }
                
                $departement = Departement::where('nom', $profil->departement)
                    ->where('actif', true)
                    ->with('responsable')
                    ->first();
                
                if ($departement && $departement->responsable && $departement->responsable->id != $profil->id) {
                    $profil->n_plus_1_id = $departement->responsable->id;
                    
                    // Calculer N+2
                    if ($departement->responsable->n_plus_1_id && $departement->responsable->n_plus_1_id != $profil->id) {
                        $profil->n_plus_2_id = $departement->responsable->n_plus_1_id;
                    }
                    
                    $profil->save();
                    $fixed++;
                    $this->info("✓ {$profil->prenom} {$profil->nom} -> N+1: {$departement->responsable->prenom} {$departement->responsable->nom} (Responsable {$profil->departement})");
                }
            }
        } else {
            $this->error('Veuillez spécifier un fichier Excel (--file=chemin) ou utiliser --use-department-manager');
            return Command::FAILURE;
        }
        
        $this->newLine();
        $this->info("Résumé:");
        $this->info("  - Profils corrigés: $fixed");
        $this->info("  - N+1 non trouvés: $notFound");
        
        if (!empty($errors)) {
            $this->newLine();
            $this->warn("Erreurs rencontrées:");
            foreach ($errors as $error) {
                $this->line("  - $error");
            }
        }
        
        return Command::SUCCESS;
    }
}
