<?php

use App\Http\Controllers\Api\ApplicationController;

Route::apiResource('applications', ApplicationController::class)
    ->names([
        'index' => 'api.applications.index',
        'store' => 'api.applications.store',
        'show' => 'api.applications.show',
        'update' => 'api.applications.update',
        'destroy' => 'api.applications.destroy',
    ]);
