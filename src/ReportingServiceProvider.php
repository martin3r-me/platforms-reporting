<?php

/**
 * Reporting Service Provider
 * 
 * Dieser Service Provider ist das Herzstück jedes Platform-Moduls.
 * 
 * WICHTIG FÜR LLMs:
 * - Dieser Service Provider folgt dem exakten Muster von HCM und Planner
 * - Alle wichtigen Schritte sind kommentiert
 * - Config wird in register() geladen (Laravel Best Practice)
 * - Modul-Registrierung erfolgt in boot()
 * 
 * ANPASSUNGEN FÜR NEUES MODUL:
 * 1. Ersetze "Reporting" durch deinen Modul-Namen (PascalCase)
 * 2. Ersetze "reporting" durch deinen Modul-Namen (kebab-case)
 * 3. Passe Namespaces an
 * 4. Füge Commands/Tools hinzu falls nötig
 * 
 * @see Platform\Core\PlatformCore für Modul-Registrierung
 * @see Platform\Core\Routing\ModuleRouter für Route-Registrierung
 */

namespace Platform\Reporting;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ReportingServiceProvider extends ServiceProvider
{
    /**
     * Register Services
     * 
     * Wird VOR boot() aufgerufen.
     * Hier sollten nur leichte Registrierungen erfolgen.
     * 
     * LARAVEL BEST PRACTICE:
     * - Config sollte hier geladen werden (mergeConfigFrom)
     * - Commands können hier registriert werden
     */
    public function register(): void
    {
        /**
         * Config laden
         * 
         * mergeConfigFrom lädt die Config aus dem Package-Verzeichnis
         * und merged sie mit der Config aus config/ (falls vorhanden).
         * 
         * WICHTIG: Muss in register() sein, nicht in boot()!
         */
        $this->mergeConfigFrom(__DIR__.'/../config/reporting.php', 'reporting');

        // Verbalization-Config (aus Core übernommen). mergeConfigFrom überschreibt
        // bestehende Keys nicht — Core gewinnt, solange es parallel läuft; fehlt
        // Core, füllt dieses Modul den Fallback. Contract-Schritt: Config-Hoheit
        // wandert vollständig hierher.
        $this->mergeConfigFrom(__DIR__.'/../config/verbalization.php', 'verbalization');

        /**
         * ReportEngine-Binding: siehe boot(). Bewusst NICHT hier in register(),
         * sondern in boot() — so überschreibt es deterministisch Cores
         * Fallback-Binding (register läuft vor boot). Hält Core frei von jedem
         * Verweis aufs reporting-Modul.
         */

        // Feed-Refresh-Command (Modul-Pendant zu core `verbalization:refresh-feeds`).
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Reporting\Console\Commands\RefreshReportingFeedsCommand::class,
            ]);
        }
    }

    /**
     * Boot Services
     * 
     * Wird NACH register() aufgerufen.
     * Hier erfolgt die eigentliche Modul-Registrierung.
     * 
     * REIHENFOLGE IST WICHTIG:
     * 1. Config prüfen (bereits in register() geladen)
     * 2. Modul bei PlatformCore registrieren
     * 3. Routes laden (nur wenn Modul registriert)
     * 4. Migrationen, Views, Livewire registrieren
     */
    public function boot(): void
    {
        // ── SWITCH: Verbalisierung fährt ab jetzt die Modul-Engine ──────────
        // In boot() (nicht register()), damit es Cores Fallback-Binding aus
        // CoreServiceProvider::register() deterministisch überschreibt (boot läuft
        // nach allen register()). Ist reporting installiert → ReportingEngine;
        // sonst bleibt Cores CoreVerbalizationEngine. Contract entfernt später den
        // Core-Fallback samt Engine.
        $this->app->singleton(
            \Platform\Core\Verbalization\Contracts\ReportEngine::class,
            \Platform\Reporting\Verbalization\ReportingEngine::class,
        );

        /**
         * SCHRITT 1: Modul-Registrierung prüfen
         * 
         * Prüft ob:
         * - Config vorhanden ist
         * - modules-Tabelle existiert (für Datenbank-Registrierung)
         * 
         * Nur wenn beide Bedingungen erfüllt, wird das Modul registriert.
         */
        if (
            config()->has('reporting.routing') &&
            config()->has('reporting.navigation') &&
            Schema::hasTable('modules')
        ) {
            /**
             * Modul bei PlatformCore registrieren
             * 
             * Dies registriert das Modul in:
             * - Der Modul-Registry (für Navigation, Sidebar)
             * - Der Datenbank (modules-Tabelle)
             * 
             * Die Config wird automatisch aus config/reporting.php geladen.
             */
            PlatformCore::registerModule([
                'key'        => 'reporting', // Eindeutiger Schlüssel
                'title'      => 'Reporting', // Anzeige-Name
                'routing'    => config('reporting.routing'),
                'guard'      => config('reporting.guard'),
                'navigation' => config('reporting.navigation'),
                'sidebar'    => config('reporting.sidebar'),
            ]);
        }

        /**
         * SCHRITT 2: Routes laden
         * 
         * Routes werden nur geladen, wenn das Modul erfolgreich registriert wurde.
         * 
         * ModuleRouter::group() erstellt automatisch:
         * - Route-Prefix (aus Config)
         * - Middleware (web, auth, etc.)
         * - Domain-Handling (für Subdomain-Modus)
         */
        if (PlatformCore::getModule('reporting')) {
            /**
             * Web-Routes (authentifiziert)
             * 
             * Standard: requireAuth = true
             * Für öffentliche Routes: requireAuth = false
             */
            ModuleRouter::group('reporting', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
            
            /**
             * API-Routes (optional)
             * 
             * Falls dein Modul API-Endpoints hat:
             * 
             * ModuleRouter::apiGroup('reporting', function () {
             *     $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
             * });
             */
        }

        // ── Öffentliche Feed-Route (Modul-Pendant zu Cores verbalization-feeds) ──
        // Nicht im ModuleRouter::group('reporting') (das setzt Auth + /reporting-
        // Prefix) — der Feed-Endpoint liegt bewusst auf /feed/{token} an der Root,
        // ohne Auth. Gleicher Pfad wie Core, eigener Route-Name (route:cache-sicher).
        // Solange Core parallel läuft, greift Cores früher gebundene Route; nach dem
        // Contract-Schritt (Core-Route entfällt) übernimmt diese.
        \Illuminate\Support\Facades\Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
            ->middleware(['web'])
            ->group(__DIR__.'/../routes/verbalization-feeds.php');

        // ── Feed-Refresh-Schedule (Modul-Pendant zu Cores Einträgen) ────────────
        // Sequenzieller Scheduler-Lauf + state_hash-Dedup im FeedService machen den
        // Parallelbetrieb mit Core sicher: Core läuft zuerst und erzeugt Outputs,
        // dieser Lauf sieht denselben Hash und skippt (kein Doppel-Output).
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function (\Illuminate\Console\Scheduling\Schedule $schedule) {
            $schedule->command('reporting:refresh-feeds --cadence=daily')->dailyAt('04:00')->withoutOverlapping();
            $schedule->command('reporting:refresh-feeds --cadence=weekly')->weeklyOn(1, '04:30')->withoutOverlapping();
        });

        /**
         * SCHRITT 3: Migrationen laden
         *
         * Lädt alle Migrationen aus database/migrations/
         * Wird automatisch bei `php artisan migrate` ausgeführt.
         */
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        /**
         * SCHRITT 4: Config veröffentlichen
         * 
         * Ermöglicht es, die Config in config/reporting.php zu überschreiben.
         * 
         * Publizieren mit:
         * php artisan vendor:publish --tag=config --provider="Platform\Reporting\ReportingServiceProvider"
         * 
         * WICHTIG: mergeConfigFrom funktioniert auch OHNE Publizierung!
         */
        $this->publishes([
            __DIR__.'/../config/reporting.php' => config_path('reporting.php'),
        ], 'config');

        /**
         * SCHRITT 5: Views laden
         * 
         * Registriert Views unter dem Namespace 'reporting'
         * 
         * Verwendung in Views:
         * @return view('reporting::livewire.dashboard')
         */
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'reporting');
        
        /**
         * SCHRITT 6: Livewire Components registrieren
         * 
         * Registriert alle Livewire-Komponenten automatisch.
         * 
         * Pattern:
         * - Datei: src/Livewire/Dashboard.php
         * - Alias: reporting.dashboard
         * 
         * Verwendung:
         * <livewire:reporting.dashboard />
         */
        $this->registerLivewireComponents();

        // Verbalization-MCP-Tools (übernommen aus Core). Idempotent: registriert
        // nur, was nicht schon (von Core) registriert ist. Beim Contract-Schritt
        // fällt der Core-Block weg und dieser hier übernimmt.
        $this->registerTools();
    }

    /**
     * Registriert die Verbalization-Tools am zentralen ToolRegistry.
     * afterResolving, damit Reihenfolge/Timing wie bei Core passt.
     */
    protected function registerTools(): void
    {
        $tools = [
            \Platform\Reporting\Tools\ListRecipesTool::class,
            \Platform\Reporting\Tools\CreateRecipeTool::class,
            \Platform\Reporting\Tools\UpdateRecipeTool::class,
            \Platform\Reporting\Tools\DeleteRecipeTool::class,
            \Platform\Reporting\Tools\ListFeedsTool::class,
            \Platform\Reporting\Tools\GetFeedTool::class,
            \Platform\Reporting\Tools\CreateFeedTool::class,
            \Platform\Reporting\Tools\UpdateFeedTool::class,
            \Platform\Reporting\Tools\DeleteFeedTool::class,
            \Platform\Reporting\Tools\RefreshFeedTool::class,
            \Platform\Reporting\Tools\ListOutputsTool::class,
            \Platform\Reporting\Tools\ListChannelsTool::class,
            \Platform\Reporting\Tools\CreateChannelTool::class,
            \Platform\Reporting\Tools\UpdateChannelTool::class,
            \Platform\Reporting\Tools\DeleteChannelTool::class,
        ];

        $this->app->afterResolving(\Platform\Core\Tools\ToolRegistry::class, function ($registry) use ($tools) {
            foreach ($tools as $cls) {
                if (! class_exists($cls)) {
                    continue;
                }
                try {
                    $tool = $this->app->make($cls);
                    if (! $registry->has($tool->getName())) {
                        $registry->register($tool);
                    }
                } catch (\Throwable $e) {
                    // still — ein Tool darf den Boot nicht sprengen.
                }
            }
        });
    }

    /**
     * Registriert alle Livewire-Komponenten automatisch
     * 
     * Scant das src/Livewire/ Verzeichnis rekursiv und registriert
     * alle PHP-Dateien als Livewire-Komponenten.
     * 
     * NAMING CONVENTION:
     * - Datei: src/Livewire/Dashboard.php
     * - Namespace: Platform\Reporting\Livewire\Dashboard
     * - Alias: reporting.dashboard
     * 
     * - Datei: src/Livewire/Entity/Index.php
     * - Namespace: Platform\Reporting\Livewire\Entity\Index
     * - Alias: reporting.entity.index
     * 
     * @return void
     */
    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Reporting\\Livewire';
        $prefix = 'reporting';

        // Prüfe ob Verzeichnis existiert
        if (!is_dir($basePath)) {
            return;
        }

        // Rekursiv alle PHP-Dateien durchsuchen
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            // Nur PHP-Dateien verarbeiten
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Relativen Pfad extrahieren
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            
            // Klassenpfad generieren (z.B. Entity\Index -> Entity\Index)
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            // Prüfe ob Klasse existiert
            if (!class_exists($class)) {
                continue;
            }

            // Alias generieren (z.B. Entity\Index -> entity.index)
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            // Livewire-Komponente registrieren
            Livewire::component($alias, $class);
        }
    }
}
