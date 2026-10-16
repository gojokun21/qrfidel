<?php

use App\Http\Controllers\Customer\BusinessPageController;
use App\Http\Controllers\Customer\CardController;
use App\Http\Controllers\Customer\EnrollmentController;
use App\Http\Controllers\Customer\MagicLinkController;
use App\Http\Controllers\Dashboard\BusinessController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\InvitationController;
use App\Http\Controllers\Dashboard\LocationController;
use App\Http\Controllers\Dashboard\ProgramController;
use App\Http\Controllers\Dashboard\TeamController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\CardController as StaffCardController;
use App\Http\Controllers\Staff\ScanController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

// Clientul (SDD §5.1, §8): guard `customer`, conectare cu magic link (ADR 009). Fără context de afacere:
// contul e global, iar fiecare acțiune primește afacerea explicit.
Route::name('customer.')->group(function () {
    Route::get('/b/{business:slug}', BusinessPageController::class)->name('business');
    Route::post('/b/{business:slug}/programs/{program}/enroll', EnrollmentController::class)
        ->scopeBindings()->middleware('auth:customer')->name('enroll');

    Route::middleware('guest:customer')->group(function () {
        Route::get('/login/magic', [MagicLinkController::class, 'create'])->name('login');
        Route::post('/login/magic', [MagicLinkController::class, 'store'])->middleware('throttle:10,1')->name('magic.send');
    });

    Route::middleware('signed')->group(function () {
        Route::get('/login/magic/verify', [MagicLinkController::class, 'show'])->name('magic.show');
        Route::post('/login/magic/verify', [MagicLinkController::class, 'consume'])->middleware('throttle:10,1')->name('magic.consume');
    });

    Route::middleware('auth:customer')->group(function () {
        Route::get('/me', [CardController::class, 'index'])->name('cards');
        // Cardul după public_id (ULID): id-urile numerice nu se pot enumera (ADR 010).
        Route::get('/me/cards/{card:public_id}', [CardController::class, 'show'])->name('cards.show');
        Route::post('/me/cards/{card:public_id}/qr', [CardController::class, 'qr'])->middleware('throttle:customer-qr')->name('cards.qr');
        Route::post('/me/logout', [MagicLinkController::class, 'destroy'])->name('logout');
    });
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

        Route::get('/programs/create', [ProgramController::class, 'create'])->name('programs.create');
        Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
        Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])->name('programs.edit');
        Route::patch('/programs/{program}', [ProgramController::class, 'update'])->name('programs.update');
        Route::post('/programs/{program}/status', [ProgramController::class, 'status'])->name('programs.status');
        Route::post('/programs/{program}/duplicate', [ProgramController::class, 'duplicate'])->name('programs.duplicate');
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->name('programs.destroy');

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::patch('/team/{membership}', [TeamController::class, 'update'])->whereNumber('membership')->name('team.update');
        Route::delete('/team/{membership}', [TeamController::class, 'destroy'])->whereNumber('membership')->name('team.destroy');

        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
    });

    Route::middleware(['business', 'role:manager'])->name('.')->group(function () {
        Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
    });
});

// Angajatul la casă (SDD §5.2, ADR 011): scanare → ecranul cardului → ștampilă / recompensă, cu tichet de scanare.
// Cardul se adresează după public_id; global scope-ul ascunde cardurile altor afaceri (404).
Route::middleware(['auth', 'verified', 'has-business', 'business', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/scan', [ScanController::class, 'show'])->name('scan');
    Route::post('/location', [ScanController::class, 'location'])->name('location');
    Route::post('/scan/resolve', [ScanController::class, 'resolve'])->middleware('throttle:staff-scan')->name('scan.resolve');

    Route::get('/cards/{card:public_id}', [StaffCardController::class, 'show'])->name('cards.show');
    Route::post('/cards/{card:public_id}/stamp', [StaffCardController::class, 'stamp'])->name('cards.stamp');
    Route::post('/cards/{card:public_id}/redeem', [StaffCardController::class, 'redeem'])->name('cards.redeem');
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
