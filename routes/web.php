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

// Routes pour les profils - Admin uniquement pour créer/éditer/supprimer
Route::middleware(['auth'])->group(function () {
    Route::resource('profils', ProfilController::class);
    Route::resource('roles', RoleController::class)->middleware('role:admin');

    // Routes pour les habilitations - Accessibles selon les rôles
    Route::resource('habilitations', HabilitationController::class);
    Route::get('habilitations/{habilitation}/etape2', [HabilitationController::class, 'etape2'])->name('habilitations.etape2');
    Route::put('habilitations/{habilitation}/etape2', [HabilitationController::class, 'updateEtape2'])->name('habilitations.update-etape2');
    Route::get('habilitations/{habilitation}/etape3', [HabilitationController::class, 'etape3'])->name('habilitations.etape3')->middleware('role:controle');
    Route::post('habilitations/{habilitation}/valider-etape3', [HabilitationController::class, 'validerEtape3'])->name('habilitations.valider-etape3')->middleware('role:controle');
    Route::get('habilitations/{habilitation}/etape4', [HabilitationController::class, 'etape4'])->name('habilitations.etape4');
    Route::post('habilitations/{habilitation}/valider-etape4', [HabilitationController::class, 'validerEtape4'])->name('habilitations.valider-etape4');
    Route::get('habilitations/{habilitation}/etape5', [HabilitationController::class, 'etape5'])->name('habilitations.etape5')->middleware('role:admin');
    Route::post('habilitations/{habilitation}/executer-etape5', [HabilitationController::class, 'executerEtape5'])->name('habilitations.executer-etape5')->middleware('role:admin');
});
