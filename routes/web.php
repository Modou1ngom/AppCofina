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
use App\Http\Controllers\HabilitationController;

Route::resource('profils', ProfilController::class);

// Routes pour les habilitations
// TODO: Réactiver le middleware 'auth' en production pour sécuriser l'accès
Route::resource('habilitations', HabilitationController::class); // ->middleware('auth');
Route::get('habilitations/{habilitation}/etape2', [HabilitationController::class, 'etape2'])->name('habilitations.etape2'); // ->middleware('auth');
Route::put('habilitations/{habilitation}/etape2', [HabilitationController::class, 'updateEtape2'])->name('habilitations.update-etape2'); // ->middleware('auth');
Route::get('habilitations/{habilitation}/etape3', [HabilitationController::class, 'etape3'])->name('habilitations.etape3'); // ->middleware('auth');
Route::post('habilitations/{habilitation}/valider-etape3', [HabilitationController::class, 'validerEtape3'])->name('habilitations.valider-etape3'); // ->middleware('auth');
Route::get('habilitations/{habilitation}/etape4', [HabilitationController::class, 'etape4'])->name('habilitations.etape4'); // ->middleware('auth');
Route::post('habilitations/{habilitation}/valider-etape4', [HabilitationController::class, 'validerEtape4'])->name('habilitations.valider-etape4'); // ->middleware('auth');
Route::get('habilitations/{habilitation}/etape5', [HabilitationController::class, 'etape5'])->name('habilitations.etape5'); // ->middleware('auth');
Route::post('habilitations/{habilitation}/executer-etape5', [HabilitationController::class, 'executerEtape5'])->name('habilitations.executer-etape5'); // ->middleware('auth');
