<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\ApplicationController;

Route::apiResource('profiles', ProfileController::class);
Route::apiResource('applications', ApplicationController::class)
    ->names([
        'index' => 'api.applications.index',
        'store' => 'api.applications.store',
        'show' => 'api.applications.show',
        'update' => 'api.applications.update',
        'destroy' => 'api.applications.destroy',
    ]);
