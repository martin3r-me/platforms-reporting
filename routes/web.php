<?php

/**
 * Reporting Web Routes
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
use Platform\Reporting\Livewire\Factory;
use Platform\Reporting\Livewire\Reports\Builder;
use Platform\Reporting\Livewire\Showcase;

/**
 * Berichte — Startseite des Moduls (Liste aller Berichte).
 */
Route::get('/', Dashboard::class)->name('reporting.dashboard');

/**
 * Bericht-Baukasten — neu anlegen / bearbeiten. Ein Bericht ist ein Feed.
 */
Route::get('/reports/new', Builder::class)->name('reporting.reports.create');
Route::get('/reports/{feed}', Builder::class)->whereNumber('feed')->name('reporting.reports.edit');

/**
 * Bausteine (Diagnose: Sammler, Perspektiven, Metrik-Provider, Kanäle).
 */
Route::get('/factory', Factory::class)->name('reporting.factory');

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
