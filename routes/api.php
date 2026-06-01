<?php

use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\Mobile\MobileAttendanceController;
use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileNotificationController;
use App\Http\Controllers\Api\Mobile\MobileProfileController;
use App\Http\Controllers\Api\Mobile\PointageMobileController;
use Illuminate\Support\Facades\Route;

Route::apiResource('applications', ApplicationController::class)
    ->names([
        'index' => 'api.applications.index',
        'store' => 'api.applications.store',
        'show' => 'api.applications.show',
        'update' => 'api.applications.update',
        'destroy' => 'api.applications.destroy',
    ]);

/*
|--------------------------------------------------------------------------
| API mobile — pointage (jeton Bearer Sanctum)
|--------------------------------------------------------------------------
| Base URL : /api/mobile/...
| En-tête : Authorization: Bearer {token}
*/
Route::prefix('mobile')->group(function (): void {
    Route::post('login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('verify-otp', [MobileAuthController::class, 'verifyOtp'])
        ->middleware('throttle:20,1');

    Route::middleware(['auth:sanctum', 'api.active'])->group(function (): void {
        Route::post('logout', [MobileAuthController::class, 'logout']);
        Route::post('register-device', [MobileAuthController::class, 'registerDevice']);

        Route::prefix('attendance')->group(function (): void {
            Route::post('checkin', [MobileAttendanceController::class, 'checkin'])->middleware('throttle:120,1');
            Route::post('checkout', [MobileAttendanceController::class, 'checkout'])->middleware('throttle:120,1');
            Route::get('today', [MobileAttendanceController::class, 'today']);
            Route::get('history', [MobileAttendanceController::class, 'history']);
        });

        Route::get('profile', [MobileProfileController::class, 'show']);
        Route::get('notifications', [MobileNotificationController::class, 'index']);
        Route::post('notifications/read-all', [MobileNotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [MobileNotificationController::class, 'markRead']);

        Route::get('pointage/sites', [PointageMobileController::class, 'sites']);
        Route::post('pointage', [PointageMobileController::class, 'store'])->middleware('throttle:120,1');
        Route::get('pointage/today', [PointageMobileController::class, 'today']);
    });
});
