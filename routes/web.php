<?php

use App\Http\Controllers\AgenceController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AvanceSalaireBaremeController;
use App\Http\Controllers\AvanceSalaireDemandeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartementController;
use App\Http\Controllers\FilialeController;
use App\Http\Controllers\HabilitationController;
use App\Http\Controllers\PointageController;
use App\Http\Controllers\PointageDeclarationController;
use App\Http\Controllers\PointageRapportController;
use App\Http\Controllers\PointageSiteController;
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
    Route::resource('filiales', FilialeController::class)->middleware('role:admin');
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

    // Suivi des personnes apparentées ou liées (décret 2008-1366) — auth ; droits fins dans les contrôleurs
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

    Route::prefix('pointage')->name('pointage.')->group(function () {
        Route::get('/', [PointageController::class, 'index'])->name('index');
        Route::post('/enregistrer', [PointageController::class, 'store'])->name('store');

        Route::get('/declarations', [PointageDeclarationController::class, 'index'])->name('declarations.index');
        Route::get('/declarations/create', [PointageDeclarationController::class, 'create'])->name('declarations.create');
        Route::post('/declarations', [PointageDeclarationController::class, 'store'])->name('declarations.store');
        Route::get('/declarations/validation-manager', [PointageDeclarationController::class, 'validationManager'])
            ->name('declarations.validation-manager');
        Route::post('/declarations/{declaration}/decision-manager', [PointageDeclarationController::class, 'decisionManager'])
            ->name('declarations.decision-manager');

        Route::middleware('role:admin,rh')->group(function () {
            Route::resource('sites', PointageSiteController::class);
            Route::post('sites/{site}/regenerer-qr', [PointageSiteController::class, 'regenererQr'])
                ->name('sites.regenerer-qr');
            Route::get('/declarations/validation-rh', [PointageDeclarationController::class, 'validationRh'])
                ->name('declarations.validation-rh');
            Route::post('/declarations/{declaration}/decision-rh', [PointageDeclarationController::class, 'decisionRh'])
                ->name('declarations.decision-rh');
            Route::get('/rapport', [PointageRapportController::class, 'index'])->name('rapport');
            Route::get('/rapport/export-quotidien', [PointageRapportController::class, 'exportQuotidien'])
                ->name('rapport.export-quotidien');
            Route::get('/rapport/export-journalier-rh', [PointageRapportController::class, 'exportJournalierRh'])
                ->name('rapport.export-journalier-rh');
            Route::get('/rapport/export-synthese-rh', [PointageRapportController::class, 'exportSyntheseRh'])
                ->name('rapport.export-synthese-rh');
        });
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
});
