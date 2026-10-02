<?php

use App\Http\Controllers\Api\EntitlementController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['success' => true, 'message' => 'Manager.ShopEra API']));

Route::prefix('v1')->middleware('apikey')->group(function () {
    Route::get('/entitlements', [EntitlementController::class, 'show'])->name('api.entitlements');
    Route::get('/theme', [EntitlementController::class, 'theme'])->name('api.theme');
    Route::post('/usage', [EntitlementController::class, 'usage'])->name('api.usage');
    Route::get('/themes', [EntitlementController::class, 'themes'])->name('api.themes');
    Route::get('/tenants', [EntitlementController::class, 'tenants'])->name('api.tenants');
    Route::put('/theme-selection', [EntitlementController::class, 'selectTheme'])->name('api.theme.select');
});
