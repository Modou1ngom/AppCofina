<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\ApplicationController;

Route::apiResource('profiles', ProfileController::class);
Route::apiResource('applications', ApplicationController::class);
