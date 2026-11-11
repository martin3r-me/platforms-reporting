<?php

use Illuminate\Support\Facades\Route;
use Platform\Reporting\Verbalization\Feed\FeedController;

// Atom-/Kanal-Feed-Endpoint — opake UUID als URL-Token. Pfad /feed/{token}
// ist von externen Readern abonniert und muss stabil bleiben. Bewusst KEINE
// NoCacheHeaders-Middleware: Feeds dürfen 5 Minuten gecached werden.
Route::get('/feed/{token}', FeedController::class)
    ->where('token', '[a-f0-9-]+(\.xml)?')
    ->name('reporting.verbalization.feed');
