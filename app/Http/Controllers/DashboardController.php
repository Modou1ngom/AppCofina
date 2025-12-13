<?php

namespace App\Http\Controllers;

use App\Helpers\FilialeHelper;
use App\Models\Habilitation;
use App\Models\Profil;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $profil = $user?->profil;

        // Construire la requête de base pour les habilitations selon le rôle
        $query = Habilitation::query();
        
        // Filtrer par filiale si une filiale est définie dans la session
        $currentFilialeId = FilialeHelper::getCurrentFilialeId();
        if ($currentFilialeId) {
            $query->where(function($q) use ($currentFilialeId) {
                $q->whereHas('requester', function($subQ) use ($currentFilialeId) {
                    $subQ->where('filiale_id', $currentFilialeId);
                })->orWhereHas('beneficiary', function($subQ) use ($currentFilialeId) {
                    $subQ->where('filiale_id', $currentFilialeId);
                });
            });
        }

        // Admin voit toutes les habilitations (mais filtrées par filiale si définie)
        if ($user && $user->isAdmin()) {
            // Pas de restriction supplémentaire pour l'admin
        } 
        // Exécuteur IT voit les habilitations approuvées par le contrôle
        elseif ($user && $user->isExecuteurIt()) {
            $query->where(function($q) {
                $q->where('status', 'approved')
                  ->orWhere('status', 'in_progress')
                  ->orWhere('executor_it_id', '!=', null);
            });
        }
        // Contrôle voit les habilitations en attente de contrôle et celles déjà contrôlées
        elseif ($user && $user->isControle()) {
            $query->where(function($q) {
                $q->where('status', 'pending_control')
                  ->orWhere('validator_control_id', '!=', null);
            });
        }
        // RH voit les habilitations où son profil est demandeur ou bénéficiaire
        elseif ($user && $user->isRh() && $profil) {
            $query->where(function($q) use ($profil) {
                $q->where('requester_profile_id', $profil->id)
                  ->orWhere('beneficiary_profile_id', $profil->id);
            });
        }
        // Métier voit ses habilitations, celles de ses subordonnés, et celles qu'il doit valider
        elseif ($profil) {
            $subordonnesIds = $profil->subordonnes()->pluck('id')->toArray();
            $subordonnesIds[] = $profil->id; // Inclure aussi le profil lui-même

            // Récupérer les profils dont l'utilisateur est N+1 ou N+2
            $profilsNPlus1 = Profil::where('n_plus_1_id', $profil->id)->pluck('id')->toArray();
            $profilsNPlus2 = Profil::where('n_plus_2_id', $profil->id)->pluck('id')->toArray();

            $query->where(function($q) use ($profil, $subordonnesIds, $profilsNPlus1, $profilsNPlus2) {
                $q->whereIn('requester_profile_id', $subordonnesIds)
                  ->orWhereIn('beneficiary_profile_id', $subordonnesIds)
                  ->orWhere('validator_n1_id', $profil->id)
                  ->orWhere('validator_n2_id', $profil->id)
                  ->orWhere(function($subQ) use ($profilsNPlus1) {
                      $subQ->whereIn('requester_profile_id', $profilsNPlus1)
                           ->where('status', 'pending_n1');
                  })
                  ->orWhere(function($subQ) use ($profilsNPlus2) {
                      $subQ->whereIn('requester_profile_id', $profilsNPlus2)
                           ->where('status', 'pending_n2');
                  });
            });
        } 
        else {
            $query->where('id', 0); // Aucune habilitation
        }

        // Statistiques générales des habilitations (filtrées selon le rôle)
        $statsHabilitations = [
            'total' => (clone $query)->count(),
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'pending_n1' => (clone $query)->where('status', 'pending_n1')->count(),
            'pending_n2' => (clone $query)->where('status', 'pending_n2')->count(),
            'pending_control' => (clone $query)->where('status', 'pending_control')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
        ];

        // Statistiques des profils (selon le rôle)
        $profilQuery = Profil::query();
        
        // Filtrer par filiale si une filiale est définie
        if ($currentFilialeId) {
            $profilQuery->where('filiale_id', $currentFilialeId);
        }
        
        if ($user && ($user->isAdmin() || $user->isRh())) {
            // Admin et RH voient tous les profils (mais filtrés par filiale si définie)
        } elseif ($profil) {
            // Métier voit son profil et ses subordonnés
            $subordonnesIds = $profil->subordonnes()->pluck('id')->toArray();
            $subordonnesIds[] = $profil->id;
            $profilQuery->whereIn('id', $subordonnesIds);
        } else {
            $profilQuery->where('id', 0);
        }

        $statsProfils = [
            'total' => (clone $profilQuery)->count(),
            'actifs' => (clone $profilQuery)->where('statut', 'actif')->count(),
            'inactifs' => (clone $profilQuery)->where('statut', 'inactif')->count(),
        ];

        // Statistiques des applications (filtrées par filiale si définie)
        $applicationQuery = Application::where('actif', true);
        if ($currentFilialeId) {
            $applicationQuery->where('filiale_id', $currentFilialeId);
        }
        $statsApplications = [
            'total' => $applicationQuery->count(),
        ];

        // Habilitations récentes (10 dernières, filtrées selon le rôle)
        $recentHabilitations = (clone $query)
            ->with(['requester', 'beneficiary'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Actions en attente selon le rôle de l'utilisateur
        $actionsEnAttente = [];

        if ($user) {
            // Pour N+1 : habilitations en attente de validation N+1
            if ($profil) {
                $habilitationsN1 = Habilitation::whereHas('requester', function($q) use ($profil, $currentFilialeId) {
                    $q->where('n_plus_1_id', $profil->id);
                    if ($currentFilialeId) {
                        $q->where('filiale_id', $currentFilialeId);
                    }
                })
                ->where('status', 'pending_n1')
                ->with(['requester', 'beneficiary'])
                ->count();
                
                if ($habilitationsN1 > 0) {
                    $actionsEnAttente[] = [
                        'type' => 'validation_n1',
                        'label' => 'Habilitations en attente de validation N+1',
                        'count' => $habilitationsN1,
                        'url' => route('habilitations.index', ['filter' => 'encours']),
                    ];
                }
            }

            // Pour N+2 : habilitations en attente de validation N+2
            if ($profil) {
                $habilitationsN2 = Habilitation::whereHas('requester', function($q) use ($profil, $currentFilialeId) {
                    $q->where('n_plus_2_id', $profil->id);
                    if ($currentFilialeId) {
                        $q->where('filiale_id', $currentFilialeId);
                    }
                })
                ->where('status', 'pending_n2')
                ->with(['requester', 'beneficiary'])
                ->count();
                
                if ($habilitationsN2 > 0) {
                    $actionsEnAttente[] = [
                        'type' => 'validation_n2',
                        'label' => 'Habilitations en attente de validation N+2',
                        'count' => $habilitationsN2,
                        'url' => route('habilitations.index', ['filter' => 'encours']),
                    ];
                }
            }

            // Pour Contrôle : habilitations en attente de contrôle
            if ($user->isControle()) {
                $habilitationsControlQuery = Habilitation::where('status', 'pending_control');
                if ($currentFilialeId) {
                    $habilitationsControlQuery->where(function($q) use ($currentFilialeId) {
                        $q->whereHas('requester', function($subQ) use ($currentFilialeId) {
                            $subQ->where('filiale_id', $currentFilialeId);
                        })->orWhereHas('beneficiary', function($subQ) use ($currentFilialeId) {
                            $subQ->where('filiale_id', $currentFilialeId);
                        });
                    });
                }
                $habilitationsControl = $habilitationsControlQuery->count();
                
                if ($habilitationsControl > 0) {
                    $actionsEnAttente[] = [
                        'type' => 'validation_control',
                        'label' => 'Habilitations en attente de contrôle permanent',
                        'count' => $habilitationsControl,
                        'url' => route('habilitations.index', ['filter' => 'encours']),
                    ];
                }
            }

            // Pour Exécuteur IT : habilitations approuvées en attente de prise en charge
            if ($user->isExecuteurIt()) {
                $habilitationsITQuery = Habilitation::where('status', 'approved');
                if ($currentFilialeId) {
                    $habilitationsITQuery->where(function($q) use ($currentFilialeId) {
                        $q->whereHas('requester', function($subQ) use ($currentFilialeId) {
                            $subQ->where('filiale_id', $currentFilialeId);
                        })->orWhereHas('beneficiary', function($subQ) use ($currentFilialeId) {
                            $subQ->where('filiale_id', $currentFilialeId);
                        });
                    });
                }
                $habilitationsIT = $habilitationsITQuery->count();
                
                if ($habilitationsIT > 0) {
                    $actionsEnAttente[] = [
                        'type' => 'execution_it',
                        'label' => 'Habilitations approuvées en attente d\'exécution IT',
                        'count' => $habilitationsIT,
                        'url' => route('habilitations.espace-it', ['filter' => 'approuvees']),
                    ];
                }
            }
        }

        // Répartition des habilitations par type de demande (filtrées selon le rôle)
        $repartitionParType = [
            'Creation' => (clone $query)->where('request_type', 'Creation')->count(),
            'Modification' => (clone $query)->where('request_type', 'Modification')->count(),
            'Desactivation' => (clone $query)->where('request_type', 'Desactivation')->count(),
            'Suppression' => (clone $query)->where('request_type', 'Suppression')->count(),
        ];

        // Habilitations par mois (6 derniers mois, filtrées selon le rôle)
        // Compatible avec MySQL et SQLite
        $driver = \DB::connection()->getDriverName();
        $moisQuery = (clone $query)->where('created_at', '>=', now()->subMonths(6));
        
        if ($driver === 'sqlite') {
            $habilitationsParMois = $moisQuery
                ->selectRaw('strftime("%Y-%m", created_at) as mois, COUNT(*) as total')
                ->groupBy('mois')
                ->orderBy('mois', 'asc')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->mois => $item->total];
                });
        } else {
            // MySQL, PostgreSQL, etc.
            $habilitationsParMois = $moisQuery
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as total')
                ->groupBy('mois')
                ->orderBy('mois', 'asc')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->mois => $item->total];
                });
        }

        return Inertia::render('Dashboard', [
            'statsHabilitations' => $statsHabilitations,
            'statsProfils' => $statsProfils,
            'statsApplications' => $statsApplications,
            'recentHabilitations' => $recentHabilitations,
            'actionsEnAttente' => $actionsEnAttente,
            'repartitionParType' => $repartitionParType,
            'habilitationsParMois' => $habilitationsParMois,
            'userRole' => $this->getUserRole($user),
        ]);
    }

    /**
     * Détermine le rôle principal de l'utilisateur pour l'affichage
     */
    private function getUserRole($user): string
    {
        if (!$user) {
            return 'guest';
        }

        if ($user->isAdmin()) {
            return 'admin';
        }
        if ($user->isRh()) {
            return 'rh';
        }
        if ($user->isControle()) {
            return 'controle';
        }
        if ($user->isExecuteurIt()) {
            return 'executeur_it';
        }
        
        return 'metier';
    }
}

