<?php

use Illuminate\Support\Facades\Route;
use Platform\Reporting\Verbalization\Feed\FeedController;

// Atom-/Kanal-Feed-Endpoint — opake UUID als URL-Token (gleicher Pfad wie Cores
// Feed-Route). Bewusst KEINE NoCacheHeaders-Middleware: Feeds dürfen 5 Minuten
// gecached werden.
//
// Eigener Route-NAME (reporting.verbalization.feed) statt core.verbalization.feed:
// doppelte Route-Namen brächen `route:cache`, solange Core parallel läuft. Der
// PFAD ist identisch — externe Reader-URLs bleiben stabil, unabhängig vom Namen.
// Solange beide registriert sind, greift Cores früher gebundene Route; nach dem
// Contract-Schritt (Core-Route entfällt) übernimmt diese.
Route::get('/feed/{token}', FeedController::class)
    ->where('token', '[a-f0-9-]+(\.xml)?')
    ->name('reporting.verbalization.feed');
