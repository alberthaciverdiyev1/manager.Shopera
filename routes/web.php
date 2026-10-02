<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.dashboard'));

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->name('login.attempt');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');

        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Site owners (customers) managed by domain
        Route::resource('owners', Admin\OwnerController::class)->except(['show']);
        Route::put('owners/{owner}/features', [Admin\OwnerController::class, 'updateFeatures'])->name('owners.features');
        Route::post('owners/{owner}/token', [Admin\OwnerController::class, 'regenerateToken'])->name('owners.token');
        Route::post('owners/{owner}/secret', [Admin\OwnerController::class, 'regenerateSecret'])->name('owners.secret');

        // Plans & the feature matrix
        Route::get('plans', [Admin\PlanController::class, 'index'])->name('plans.index');
        Route::get('plans/{plan}/edit', [Admin\PlanController::class, 'edit'])->name('plans.edit');
        Route::put('plans/{plan}', [Admin\PlanController::class, 'update'])->name('plans.update');

        // Feature catalogue
        Route::get('features', [Admin\FeatureController::class, 'index'])->name('features.index');
        Route::get('features/create', [Admin\FeatureController::class, 'create'])->name('features.create');
        Route::post('features', [Admin\FeatureController::class, 'store'])->name('features.store');
        Route::get('features/{feature}/edit', [Admin\FeatureController::class, 'edit'])->name('features.edit');
        Route::put('features/{feature}', [Admin\FeatureController::class, 'update'])->name('features.update');
        Route::delete('features/{feature}', [Admin\FeatureController::class, 'destroy'])->name('features.destroy');

        // Themes (presets)
        Route::resource('promo-blocks', Admin\PromoBlockController::class)->except(['show'])->parameters(['promo-blocks' => 'promo_block']);

        Route::get('themes', [Admin\ThemeController::class, 'index'])->name('themes.index');
        Route::get('themes/create', [Admin\ThemeController::class, 'create'])->name('themes.create');
        Route::post('themes', [Admin\ThemeController::class, 'store'])->name('themes.store');
        Route::get('themes/{theme}/edit', [Admin\ThemeController::class, 'edit'])->name('themes.edit');
        Route::put('themes/{theme}', [Admin\ThemeController::class, 'update'])->name('themes.update');
        Route::delete('themes/{theme}', [Admin\ThemeController::class, 'destroy'])->name('themes.destroy');
        Route::post('themes/{theme}/default', [Admin\ThemeController::class, 'makeDefault'])->name('themes.default');
    });
});
