<?php

use App\Http\Controllers\AgenceController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AvanceSalaireBaremeController;
use App\Http\Controllers\AvanceSalaireDemandeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartementController;
use App\Http\Controllers\EnqueteSatisfactionController;
use App\Http\Controllers\FilialeController;
use App\Http\Controllers\HabilitationController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SigEncoursConformiteController;
use App\Http\Controllers\SigLookupController;
use App\Http\Controllers\SigPersonneLieeController;
use App\Http\Controllers\SigStaffController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Enquête de satisfaction IT — accessible sans authentification (lien partagé au staff)
Route::prefix('enquete-satisfaction')->name('enquete-satisfaction.')->group(function () {
    Route::get('/', [EnqueteSatisfactionController::class, 'create'])->name('create');
    Route::post('/', [EnqueteSatisfactionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('store');
    Route::get('/merci', [EnqueteSatisfactionController::class, 'merci'])->name('merci');
});

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

// Route pour le changement de mot de passe obligatoire
Route::middleware(['auth'])->group(function () {
    Route::get('password/change', [\App\Http\Controllers\ChangePasswordController::class, 'show'])->name('password.change');
    Route::post('password/change', [\App\Http\Controllers\ChangePasswordController::class, 'update'])->name('password.change.update');
});

require __DIR__.'/settings.php';

// Routes pour les profils - Admin et RH peuvent créer/éditer/supprimer
Route::middleware(['auth'])->group(function () {
    Route::get('profils/import', [ProfilController::class, 'showImport'])->name('profils.import')->middleware('role:admin,rh');
    Route::post('profils/import', [ProfilController::class, 'import'])->name('profils.import.store')->middleware('role:admin,rh');
    Route::get('profils/export', [ProfilController::class, 'export'])->name('profils.export')->middleware('role:admin,rh');
    Route::resource('profils', ProfilController::class)->middleware('role:admin,rh');
    Route::resource('roles', RoleController::class)->middleware('role:admin');
    Route::resource('departements', DepartementController::class)->middleware('role:admin');
    Route::resource('agences', AgenceController::class)->middleware('role:admin');
    Route::resource('filiales', FilialeController::class)->middleware('role:super_admin');
    Route::resource('applications', ApplicationController::class)
        ->middleware('role:admin')
        ->names([
            'index' => 'applications.index',
            'create' => 'applications.create',
            'store' => 'applications.store',
            'show' => 'applications.show',
            'edit' => 'applications.edit',
            'update' => 'applications.update',
            'destroy' => 'applications.destroy',
        ]);

    Route::prefix('avances-salaire')->name('avances-salaire.')->group(function () {
        Route::get('/', [AvanceSalaireDemandeController::class, 'index'])->name('index');
        Route::get('/create', [AvanceSalaireDemandeController::class, 'create'])->name('create');
        Route::post('/', [AvanceSalaireDemandeController::class, 'store'])->name('store');
        Route::get('/validation-rh', [AvanceSalaireDemandeController::class, 'validationRh'])
            ->middleware('role:admin,rh')
            ->name('validation-rh');
        Route::get('/integration-rh', [AvanceSalaireDemandeController::class, 'priseEnChargeRh'])
            ->middleware('role:admin,rh')
            ->name('integration-rh');
        Route::post('/integration-rh/envoyer-template-externe', [AvanceSalaireDemandeController::class, 'envoyerTemplateVersIntegrationExterne'])
            ->middleware('role:admin,rh')
            ->name('integration-rh.envoyer-template-externe');
        Route::get('/validation-finance', [AvanceSalaireDemandeController::class, 'validationFinance'])
            ->middleware('role:admin,finance,md')
            ->name('validation-finance');
        Route::get('/parametrage', [AvanceSalaireBaremeController::class, 'index'])
            ->middleware('role:admin,rh')
            ->name('parametrage.index');
        Route::post('/parametrage', [AvanceSalaireBaremeController::class, 'store'])
            ->middleware('role:admin,rh')
            ->name('parametrage.store');
        Route::patch('/parametrage/{bareme}', [AvanceSalaireBaremeController::class, 'update'])
            ->middleware('role:admin,rh')
            ->name('parametrage.update');
        Route::delete('/parametrage/{bareme}', [AvanceSalaireBaremeController::class, 'destroy'])
            ->middleware('role:admin,rh')
            ->name('parametrage.destroy');
        Route::get('/{avance_salaire_demande}', [AvanceSalaireDemandeController::class, 'show'])->name('show');
        Route::patch('/{avance_salaire_demande}', [AvanceSalaireDemandeController::class, 'update'])->name('update');
        Route::delete('/{avance_salaire_demande}', [AvanceSalaireDemandeController::class, 'destroy'])
            ->middleware('role:admin')
            ->name('destroy');
        Route::post('/{avance_salaire_demande}/soumettre', [AvanceSalaireDemandeController::class, 'soumettre'])->name('soumettre');
        Route::post('/{avance_salaire_demande}/decision-rh', [AvanceSalaireDemandeController::class, 'decisionRh'])
            ->middleware('role:admin,rh')
            ->name('decision-rh');
        Route::get('/{avance_salaire_demande}/integration-rh/form', [AvanceSalaireDemandeController::class, 'integrationRhForm'])
            ->middleware('role:admin,rh')
            ->name('integration-rh.form');
        Route::post('/{avance_salaire_demande}/integration-rh', [AvanceSalaireDemandeController::class, 'marquerPriseEnChargeRh'])
            ->middleware('role:admin,rh')
            ->name('integration-rh.store');
        Route::post('/{avance_salaire_demande}/terminer-integration-rh', [AvanceSalaireDemandeController::class, 'terminerTraitementRh'])
            ->middleware('role:admin,rh')
            ->name('terminer-integration-rh');
        Route::post('/{avance_salaire_demande}/decision-finance', [AvanceSalaireDemandeController::class, 'decisionFinance'])
            ->middleware('role:admin,finance')
            ->name('decision-finance');
        Route::post('/{avance_salaire_demande}/reprendre', [AvanceSalaireDemandeController::class, 'reprendre'])
            ->middleware('role:admin,rh,finance,md')
            ->name('reprendre');
        Route::post('/{avance_salaire_demande}/signature', [AvanceSalaireDemandeController::class, 'signer'])->name('signature');
    });

    Route::prefix('suivi-signature')->name('suivi-signature.')->group(function () {
        Route::post('lookup-client', [SigLookupController::class, 'lookup'])->name('lookup-client');
        Route::post('personne-liee/resolve-matricule', [SigLookupController::class, 'resolvePersonneLieeParMatricule'])
            ->name('personne-liee.resolve-matricule');
        Route::get('mes-personnes-liees', [SigStaffController::class, 'mesPersonnesLiees'])->name('staff.mes-personnes-liees');
        Route::get('staff/manuel/create', [SigStaffController::class, 'createManuel'])
            ->middleware('role:admin,conformite')
            ->name('staff.manuel.create');
        Route::post('staff/manuel', [SigStaffController::class, 'storeManuel'])
            ->middleware('role:admin,conformite')
            ->name('staff.manuel.store');
        Route::post('staff/ma-fiche/initialiser', [SigStaffController::class, 'initialiserMaFiche'])->name('staff.ma-fiche.initialiser');
        Route::post('staff/ma-fiche/synchroniser-client-si', [SigStaffController::class, 'synchroniserMaFicheClientSi'])
            ->name('staff.ma-fiche.synchroniser-client-si');
        Route::get('conformite/rapport-encours', [SigEncoursConformiteController::class, 'rapport'])->name('conformite.rapport-encours');
        Route::get('conformite/rapport-encours/export', [SigEncoursConformiteController::class, 'exportCsv'])->name('conformite.rapport-encours.export');
        Route::post('staff/{staff}/conformite-encours/commentaire', [SigEncoursConformiteController::class, 'storeCommentaire'])
            ->name('staff.conformite-encours.commentaire');
        Route::post('staff/{staff}/personnes-liees', [SigStaffController::class, 'attachPersonne'])->name('staff.personnes-liees.attach');
        Route::delete('staff/{staff}/personnes-liees/{personneLiee}', [SigStaffController::class, 'detachPersonne'])->name('staff.personnes-liees.detach');
        Route::resource('staff', SigStaffController::class);
        Route::resource('personnes-liees', SigPersonneLieeController::class)->parameters([
            'personnes-liees' => 'personneLiee',
        ]);
    });

    Route::resource('users', UserController::class)->middleware('role:admin');
    Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle')->middleware('role:admin');

    // Routes pour les habilitations - Accessibles selon les rôles
    // IMPORTANT: Cette route doit être définie AVANT la route resource pour éviter les conflits
    Route::get('habilitations/select-beneficiary', [HabilitationController::class, 'selectBeneficiary'])->name('habilitations.select-beneficiary');
    Route::get('api/habilitations/subordonnes', [HabilitationController::class, 'getSubordonnes'])->name('habilitations.api.subordonnes')->middleware('auth');
    Route::get('habilitations/espace-it', [HabilitationController::class, 'espaceIt'])->name('habilitations.espace-it')->middleware('role:admin,executeur_it,it');
    Route::resource('habilitations', HabilitationController::class);
    Route::get('habilitations/{habilitation}/etape2', [HabilitationController::class, 'etape2'])->name('habilitations.etape2');
    Route::put('habilitations/{habilitation}/etape2', [HabilitationController::class, 'updateEtape2'])->name('habilitations.update-etape2');
    Route::get('habilitations/{habilitation}/etape3', [HabilitationController::class, 'etape3'])->name('habilitations.etape3');
    Route::post('habilitations/{habilitation}/valider-etape3', [HabilitationController::class, 'validerEtape3'])->name('habilitations.valider-etape3');
    Route::get('habilitations/{habilitation}/etape4', [HabilitationController::class, 'etape4'])->name('habilitations.etape4');
    Route::post('habilitations/{habilitation}/valider-etape4', [HabilitationController::class, 'validerEtape4'])->name('habilitations.valider-etape4');
    Route::get('habilitations/{habilitation}/etape5', [HabilitationController::class, 'etape5'])->name('habilitations.etape5')->middleware('role:controle');
    Route::post('habilitations/{habilitation}/valider-etape5', [HabilitationController::class, 'validerEtape5'])->name('habilitations.valider-etape5')->middleware('role:controle');
    Route::post('habilitations/{habilitation}/prendre-en-charge', [HabilitationController::class, 'prendreEnCharge'])->name('habilitations.prendre-en-charge')->middleware('role:admin,executeur_it,it');
    Route::get('habilitations/{habilitation}/etape6', [HabilitationController::class, 'etape6'])->name('habilitations.etape6')->middleware('role:admin,executeur_it,it');
    Route::post('habilitations/{habilitation}/executer-etape6', [HabilitationController::class, 'executerEtape6'])->name('habilitations.executer-etape6')->middleware('role:admin,executeur_it,it');
    Route::get('habilitations/{habilitation}/pdf', [HabilitationController::class, 'downloadPdf'])->name('habilitations.pdf');

    // MODULE DE GESTION DES MISSIONS
    Route::prefix('missions')->name('missions.')->group(function () {
        
        // ACCÈS COLLABORATEURS ET MANAGERS ---
        Route::get('/', [\App\Http\Controllers\MissionController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\MissionController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\MissionController::class, 'store'])->name('store');

        // Files d'attente métiers (avant /{mission})
        Route::get('/validation/rh', [\App\Http\Controllers\MissionController::class, 'vueRh'])->name('validation-rh');
        Route::get('/validation/rh-logistique', [\App\Http\Controllers\MissionController::class, 'vueRhLogistique'])->name('validation-rh-logistique');
        Route::get('/validation/signature-rrh', [\App\Http\Controllers\MissionController::class, 'vueSignatureRrh'])->name('validation-signature-rrh');
        Route::get('/validation/n1', [\App\Http\Controllers\MissionController::class, 'vueValidationN1'])->name('validation-n1');
        Route::get('/validation/dga', [\App\Http\Controllers\MissionController::class, 'vueDga'])->name('validation-dga');
        Route::get('/validation/md', [\App\Http\Controllers\MissionController::class, 'vueMd'])->name('validation-md');
        Route::get('/validation/facilities', [\App\Http\Controllers\MissionController::class, 'vueFacilities'])->middleware('logistique')->name('validation-facilities');
        Route::get('/validation/facilities/{mission}', [\App\Http\Controllers\MissionController::class, 'traitementFacilities'])->middleware('logistique')->name('traitement-facilities');
        Route::get('/validation/finance', [\App\Http\Controllers\MissionController::class, 'vueFinance'])->name('validation-finance');
        Route::get('/recap-logistique', [\App\Http\Controllers\MissionController::class, 'recapLogistique'])->name('recap-logistique');
        Route::get('/rapports', [\App\Http\Controllers\MissionController::class, 'vueRapportsMission'])->name('rapports');
        Route::get('/espace-missionnaire', [\App\Http\Controllers\MissionController::class, 'espaceMissionnaire'])->name('espace-missionnaire');
        Route::get('/traitees', [\App\Http\Controllers\MissionController::class, 'vueMissionsTraitees'])->name('traitees');
        Route::get('/traitees/recap', [\App\Http\Controllers\MissionController::class, 'recapMissionsTraitees'])->name('traitees-recap');

        Route::get('/{mission}', [\App\Http\Controllers\MissionController::class, 'show'])->name('show');

        // Actions du Workflow (6 niveaux)
        Route::post('/{mission}/valider', [\App\Http\Controllers\MissionController::class, 'valider'])->name('valider');
        Route::post('/{mission}/valider-dga', [\App\Http\Controllers\MissionController::class, 'validerDga'])->name('valider-dga');
        Route::post('/{mission}/valider-md', [\App\Http\Controllers\MissionController::class, 'validerMd'])->name('valider-md');
        Route::post('/{mission}/facilities', [\App\Http\Controllers\MissionController::class, 'marquerPriseEnChargeFacilities'])->middleware('logistique')->name('facilities');
        Route::post('/{mission}/valider-rh-logistique', [\App\Http\Controllers\MissionController::class, 'validerRhLogistique'])->name('valider-rh-logistique');
        Route::post('/{mission}/signer-ordre-rrh', [\App\Http\Controllers\MissionController::class, 'signerOrdreRrh'])->name('signer-ordre-rrh');
        Route::post('/{mission}/valider-finance', [\App\Http\Controllers\MissionController::class, 'validerLogistiqueFinance'])->name('valider-finance');
        Route::post('/{mission}/rapport', [\App\Http\Controllers\MissionController::class, 'soumettreRapportMission'])->name('soumettre-rapport');
        Route::post('/{mission}/valider-rapport', [\App\Http\Controllers\MissionController::class, 'validerRapportMission'])->name('valider-rapport');
        Route::post('/{mission}/modifier-duree', [\App\Http\Controllers\MissionController::class, 'modifierDureeMission'])->name('modifier-duree');
        Route::get('/{mission}/rapport-preview', [\App\Http\Controllers\MissionController::class, 'apercuRapportMission'])->name('rapport-preview');
        Route::get('/{mission}/rapport-pdf', [\App\Http\Controllers\MissionController::class, 'telechargerRapportPdf'])->name('rapport-pdf');
        Route::get('/{mission}/rapport-pieces/{pieceJointe}', [\App\Http\Controllers\MissionController::class, 'telechargerPieceJointeRapport'])->name('rapport-piece-jointe');
        Route::post('/{mission}/renvoyer', [\App\Http\Controllers\MissionController::class, 'renvoyer'])->name('renvoyer');
        Route::post('/{mission}/rejeter', [\App\Http\Controllers\MissionController::class, 'rejeter'])->name('rejeter');

        // GESTION DES IMPRESSIONS PDF ---
        Route::get('/{mission}/fiche-validation', [\App\Http\Controllers\MissionController::class, 'apercuFicheValidation'])->name('fiche-validation');
        Route::get('/{mission}/ordre-preview', [\App\Http\Controllers\MissionController::class, 'apercuOrdreMission'])->name('ordre-preview');
        Route::get('/{mission}/ordre-prolongation-preview', [\App\Http\Controllers\MissionController::class, 'apercuOrdreProlongation'])->name('ordre-prolongation-preview');
        Route::get('/{mission}/pdf', [\App\Http\Controllers\MissionController::class, 'telechargerPdf'])->name('pdf');

        // GESTION DES MODIFICATIONS
        Route::get('/{mission}/edit', [\App\Http\Controllers\MissionController::class, 'edit'])->name('edit');
        Route::put('/{mission}', [\App\Http\Controllers\MissionController::class, 'update'])->name('update');
        Route::delete('/{mission}', [\App\Http\Controllers\MissionController::class, 'destroy'])->name('destroy');

    });


    Route::prefix('enquete-satisfaction')->name('enquete-satisfaction.')->middleware('role:admin,executeur_it,it')->group(function () {
        Route::get('/reponses', [EnqueteSatisfactionController::class, 'index'])->name('index');
        Route::get('/reponses/{enqueteSatisfaction}', [EnqueteSatisfactionController::class, 'show'])->name('show');
    });
});
