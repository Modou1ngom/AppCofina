<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';

use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\HabilitationController;
use App\Http\Controllers\DepartementController;
use App\Http\Controllers\AgenceController;
use App\Http\Controllers\FilialeController;
use App\Http\Controllers\UserController;

// Routes pour les profils - Admin et RH peuvent créer/éditer/supprimer
Route::middleware(['auth'])->group(function () {
    Route::resource('profils', ProfilController::class)->middleware('role:admin,rh');
    Route::resource('roles', RoleController::class)->middleware('role:admin');
    Route::resource('departements', DepartementController::class)->middleware('role:admin');
    Route::resource('agences', AgenceController::class)->middleware('role:admin');
    Route::resource('filiales', FilialeController::class)->middleware('role:admin');
    Route::resource('users', UserController::class)->middleware('role:admin');
    Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle')->middleware('role:admin');

    // Routes pour les habilitations - Accessibles selon les rôles
    // IMPORTANT: Cette route doit être définie AVANT la route resource pour éviter les conflits
    Route::get('habilitations/select-beneficiary', [HabilitationController::class, 'selectBeneficiary'])->name('habilitations.select-beneficiary');
    Route::get('api/habilitations/subordonnes', [HabilitationController::class, 'getSubordonnes'])->name('habilitations.api.subordonnes')->middleware('auth');
    Route::resource('habilitations', HabilitationController::class);
    Route::get('habilitations/{habilitation}/etape2', [HabilitationController::class, 'etape2'])->name('habilitations.etape2');
    Route::put('habilitations/{habilitation}/etape2', [HabilitationController::class, 'updateEtape2'])->name('habilitations.update-etape2');
    Route::get('habilitations/{habilitation}/etape3', [HabilitationController::class, 'etape3'])->name('habilitations.etape3');
    Route::post('habilitations/{habilitation}/valider-etape3', [HabilitationController::class, 'validerEtape3'])->name('habilitations.valider-etape3');
    Route::get('habilitations/{habilitation}/etape4', [HabilitationController::class, 'etape4'])->name('habilitations.etape4');
    Route::post('habilitations/{habilitation}/valider-etape4', [HabilitationController::class, 'validerEtape4'])->name('habilitations.valider-etape4');
    Route::get('habilitations/{habilitation}/etape5', [HabilitationController::class, 'etape5'])->name('habilitations.etape5')->middleware('role:controle');
    Route::post('habilitations/{habilitation}/valider-etape5', [HabilitationController::class, 'validerEtape5'])->name('habilitations.valider-etape5')->middleware('role:controle');
    Route::get('habilitations/{habilitation}/etape6', [HabilitationController::class, 'etape6'])->name('habilitations.etape6')->middleware('role:admin');
    Route::post('habilitations/{habilitation}/executer-etape6', [HabilitationController::class, 'executerEtape6'])->name('habilitations.executer-etape6')->middleware('role:admin');
});
