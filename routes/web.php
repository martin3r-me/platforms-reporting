<?php

/**
 * Module Template Web Routes
 * 
 * Diese Datei definiert alle Web-Routes für das Modul.
 * 
 * WICHTIG FÜR LLMs:
 * - Routes werden automatisch mit dem Modul-Prefix versehen (aus Config)
 * - Middleware wird automatisch hinzugefügt (web, auth, etc.)
 * - Route-Namen sollten mit dem Modul-Prefix beginnen
 * 
 * BEISPIEL:
 * Route::get('/', Dashboard::class)->name('reporting.dashboard');
 * 
 * Wird zu: /reporting/ (wenn prefix = 'reporting')
 * 
 * @see Platform\Core\Routing\ModuleRouter für Details
 */

use Platform\Reporting\Livewire\Dashboard;
use Platform\Reporting\Livewire\Showcase;

/**
 * Dashboard Route
 *
 * Hauptübersicht des Moduls
 */
Route::get('/', Dashboard::class)->name('reporting.dashboard');

/**
 * nx-Showcase Route
 *
 * Lebendes Verzeichnis aller x-nx-* Komponenten (Design-Referenz).
 */
Route::get('/showcase', Showcase::class)->name('reporting.showcase');

/**
 * Weitere Routes hinzufügen:
 *
 * Route::get('/entities', Entity\Index::class)->name('reporting.entities.index');
 * Route::get('/entities/{entity}', Entity\Show::class)->name('reporting.entities.show');
 */
