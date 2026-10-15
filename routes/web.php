<?php

use App\Http\Controllers\Dashboard\BusinessController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\InvitationController;
use App\Http\Controllers\Dashboard\LocationController;
use App\Http\Controllers\Dashboard\TeamController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Dashboard owner/manager (SDD §8). Rutele cu `business` rulează în contextul afacerii curente (ADR 003).
Route::middleware(['auth', 'verified'])->prefix('dashboard')->name('dashboard')->group(function () {
    Route::get('/', DashboardController::class)->middleware(['has-business', 'business']);

    // Self-serve: orice user verificat își poate crea o afacere (ADR 006).
    Route::get('/business/create', [BusinessController::class, 'create'])->name('.business.create');
    Route::post('/business', [BusinessController::class, 'store'])->name('.business.store');
    Route::post('/business/switch', [BusinessController::class, 'switch'])->name('.business.switch');

    Route::middleware(['business', 'role:owner'])->name('.')->group(function () {
        Route::get('/business', [BusinessController::class, 'edit'])->name('business.edit');
        Route::patch('/business', [BusinessController::class, 'update'])->name('business.update');

        Route::get('/locations/create', [LocationController::class, 'create'])->name('locations.create');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::get('/locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
        Route::patch('/locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
        Route::post('/locations/{location}/counter-token', [LocationController::class, 'regenerateCounterToken'])->name('locations.counter-token');

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::patch('/team/{membership}', [TeamController::class, 'update'])->whereNumber('membership')->name('team.update');
        Route::delete('/team/{membership}', [TeamController::class, 'destroy'])->whereNumber('membership')->name('team.destroy');

        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
    });

    Route::middleware(['business', 'role:manager'])->name('.')->group(function () {
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
    });
});

// Linkul din emailul de invitație (public: invitatul poate să nu aibă încă un cont).
Route::prefix('invitations/{token}')->name('invitations.')->group(function () {
    Route::get('/', [InvitationAcceptController::class, 'show'])->name('show');
    Route::post('/accept', [InvitationAcceptController::class, 'accept'])->middleware('auth')->name('accept');
    Route::post('/register', [InvitationAcceptController::class, 'register'])->middleware('throttle:6,1')->name('register');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
