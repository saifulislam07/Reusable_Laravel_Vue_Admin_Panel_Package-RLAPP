<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// The receiving end of <x-admin::contact-form /> (Phase 16) -- throttled
// since, unlike everything else in this file, it's a public POST endpoint
// with no auth in front of it.
Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact-form')
    ->name('contact.store');

// Live-served from Settings > SEO (Phase 14), not a static file -- an
// edit there takes effect immediately, no regeneration step like
// sitemap.xml needs.
Route::get('robots.txt', fn () => response(
    (string) setting('robots_txt', "User-agent: *\nDisallow: /admin/"),
    200,
    ['Content-Type' => 'text/plain'],
))->name('robots');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
