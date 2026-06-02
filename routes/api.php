<?php

use App\Http\Controllers\Api\ApplicationController;
use Illuminate\Support\Facades\Route;

Route::apiResource('applications', ApplicationController::class)
    ->names([
        'index' => 'api.applications.index',
        'store' => 'api.applications.store',
        'show' => 'api.applications.show',
        'update' => 'api.applications.update',
        'destroy' => 'api.applications.destroy',
    ]);
