# LLM Guide für Reporting

Diese Datei ist speziell für **Large Language Models (LLMs)** geschrieben, um das Verständnis und die Arbeit mit diesem Template zu erleichtern.

## 🎯 Zweck dieses Templates

Dieses Template ist eine **minimale, vollständige Vorlage** für neue Platform-Module. Es zeigt:
- ✅ Wie ein Modul strukturiert wird
- ✅ Welche Dateien benötigt werden
- ✅ Wie die Integration funktioniert
- ✅ Welche Patterns verwendet werden

## 📐 Architektur-Übersicht

### Service Provider Pattern

```
ReportingServiceProvider
├── register()          # Config laden (Laravel Best Practice)
└── boot()              # Modul-Registrierung & Setup
    ├── PlatformCore::registerModule()  # Modul registrieren
    ├── ModuleRouter::group()            # Routes laden
    ├── loadMigrationsFrom()             # Migrationen
    ├── loadViewsFrom()                  # Views
    └── registerLivewireComponents()     # Livewire auto-registrieren
```

### Route Pattern

```
Route-Definition: Route::get('/', Dashboard::class)
    ↓
ModuleRouter::group() fügt automatisch hinzu:
    - Prefix: /reporting
    - Middleware: web, auth, etc.
    ↓
Finale Route: /reporting/
```

### Livewire Component Pattern

```
Datei: src/Livewire/Dashboard.php
    ↓
Auto-Registrierung via registerLivewireComponents()
    ↓
Alias: reporting.dashboard
    ↓
Verwendung: <livewire:reporting.dashboard />
```

## 🔄 Workflow für neues Modul

### Schritt 1: Kopieren
```bash
cp -r reporting dein-modul-name
```

### Schritt 2: Suchen & Ersetzen

**In ALLEN Dateien ersetzen:**
- `Reporting` → `DeinModulName` (PascalCase, Namespace)
- `reporting` → `dein-modul-name` (kebab-case, Routes, Config)
- `reporting` → `dein_modul_name` (snake_case, Config-Keys)

**Wichtige Dateien:**
- `composer.json` - Name, Namespace, Provider
- `config/reporting.php` → umbenennen & anpassen
- `src/ReportingServiceProvider.php` → umbenennen & anpassen
- Alle PHP-Dateien: Namespace ändern
- Alle Blade-Dateien: `reporting::` → `dein-modul-name::`

### Schritt 3: Composer registrieren

In Hauptanwendung `composer.json`:
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../platform/modules/dein-modul-name"
    }
  ],
  "require": {
    "martin3r/platform-dein-modul-name": "dev-main"
  }
}
```

Dann: `composer update`

## 📝 Code-Patterns

### 1. Service Provider

**Pattern:**
```php
public function register(): void {
    // Config laden (MUSS in register() sein!)
    $this->mergeConfigFrom(...);
}

public function boot(): void {
    // 1. Prüfen ob Config & DB vorhanden
    if (config()->has(...) && Schema::hasTable('modules')) {
        // 2. Modul registrieren
        PlatformCore::registerModule([...]);
    }
    
    // 3. Routes laden (nur wenn registriert)
    if (PlatformCore::getModule('...')) {
        ModuleRouter::group('...', function () {
            $this->loadRoutesFrom(...);
        });
    }
    
    // 4. Rest registrieren
    $this->loadMigrationsFrom(...);
    $this->loadViewsFrom(...);
    $this->registerLivewireComponents();
}
```

### 2. Livewire Component

**Pattern:**
```php
class Dashboard extends Component {
    public function render() {
        $user = Auth::user();
        $team = $user->currentTeam;
        
        // Daten laden
        $data = YourModel::where('team_id', $team->id)->get();
        
        return view('modul-name::livewire.dashboard', [
            'data' => $data,
        ])->layout('platform::layouts.app');
    }
}
```

### 3. View mit Sidebars

**Pattern:**
```blade
<x-ui-page>
    <x-slot name="navbar">...</x-slot>
    <x-slot name="actionbar">...</x-slot>          {{-- Breadcrumb + 1 x-nx-button (oder x-nx-dropdown bei ≥2) --}}
    <x-ui-page-container width="contained">
        {{-- Inhalt: x-nx-* Bausteine --}}
    </x-ui-page-container>
    <x-slot name="sidebar">...</x-slot>
    <x-slot name="activity">...</x-slot>

    {{-- Modals (x-nx-modal) IMMER innerhalb von x-ui-page! --}}
    <x-nx-modal wire:model="showCreate">...</x-nx-modal>
</x-ui-page>
```

### 4. Route-Definition

**Pattern:**
```php
Route::get('/', Dashboard::class)->name('modul-name.dashboard');
Route::get('/entities', Entity\Index::class)->name('modul-name.entities.index');
```

## 🎨 UI-Komponenten (nx-Design-System)

**Grundregel:** Rahmen/Shell = `x-ui-page*` (bleiben `x-ui-*`, sind auf nx-Tokens).
**Inhalt = `x-nx-*` + `var(--nx-*)` Tokens.** Keine `var(--ui-*)`/ENV-Farben, keine Gradients/Schatten.
Voll dokumentiert in `DESIGN.md` und `platform/ui-tailwind/docs/nx-design-system.md`.

### Shell (Rahmen — unverändert übernehmen)

- `x-ui-page` - Haupt-Container
- `x-ui-page-navbar` - Navbar
- `x-ui-page-actionbar` - Actionbar (Breadcrumb + Aktionen; default-Slot = rechts)
- `x-ui-page-container` - Hauptinhalt (`width="contained"|"full"`)
- `x-ui-page-sidebar` - Sidebar (links/rechts)
- `x-ui-sidebar-list` / `x-ui-sidebar-item` - Sidebar-Navigation

### Content-Bausteine (`x-nx-*`)

- `x-nx-card` - Container (`flush`, `hover`)
- `x-nx-button` - Button (`variant` primary/secondary/ghost/danger · `size` sm/md · `icon` · `href`)
- `x-nx-badge` - Status-Pille (`variant` · `dot`)
- `x-nx-table` (+ header/row/cell) - Register/Ledger-Daten
- `x-nx-list-item` - Katalog/Entitäten mit Identität (in `x-nx-card flush`)
- `x-nx-stat` (+ `-grid`) - Kennzahlen
- `x-nx-section` - ruhiger Kopf über einem Container
- `x-nx-empty` - Leerzustand
- `x-nx-callout` - Hinweis/Attention (bedeutungstragende Farbe)
- `x-nx-modal` - Dialog (`size` sm/md/lg/xl · `wire:model`)
- `x-nx-dropdown` (+ `-item`) - Aktionsmenü (bei ≥2 Aktionen)
- `x-nx-input-text` / `-number` / `-date` / `-textarea` / `-select` / `-checkbox` - Formularfelder

### Verwendung

```blade
<x-nx-button variant="primary" size="sm" :href="route('...')">
    Button Text
</x-nx-button>

<x-nx-input-text
    name="field_name"
    label="Label"
    wire:model="fieldName"
    placeholder="..."
    required
/>
```

## 🔍 Häufige Probleme & Lösungen

### Problem: Routes funktionieren nicht

**Lösung:**
1. Config publiziert? → `php artisan vendor:publish --tag=config`
2. Config-Cache geleert? → `php artisan config:clear`
3. Route-Cache geleert? → `php artisan route:clear`

### Problem: Livewire Component nicht gefunden

**Lösung:**
1. Service Provider registriert? → Prüfe `composer.json`
2. `composer dump-autoload` ausgeführt?
3. Klasse existiert? → Prüfe Namespace

### Problem: Multiple Root Elements Error

**Lösung:**
- Modals müssen **innerhalb** von `<x-ui-page>` sein!
- Nicht außerhalb!

### Problem: Config nicht gefunden

**Lösung:**
- `mergeConfigFrom` muss in `register()` sein, nicht `boot()`!
- Config-Datei muss existieren

## 📚 Referenzen

### Ähnliche Module zum Lernen

- **HCM** (`platform/modules/hcm`) - Komplexeres Beispiel
- **Planner** (`platform/modules/planner`) - Modals, erweiterte Features
- **Location** (`platform/modules/location`) - Aktuelles Beispiel

### Core-Klassen

- `Platform\Core\PlatformCore` - Modul-Registrierung
- `Platform\Core\Routing\ModuleRouter` - Route-Handling
- `Platform\ActivityLog\Traits\LogsActivity` - Activity Logging

## ✅ Checkliste für LLMs

Wenn du ein neues Modul erstellst:

1. [ ] Template kopiert
2. [ ] Alle Namespaces angepasst
3. [ ] Config angepasst
4. [ ] Service Provider angepasst
5. [ ] Routes angepasst
6. [ ] Views angepasst
7. [ ] Composer registriert
8. [ ] `composer dump-autoload` ausgeführt
9. [ ] Config-Cache geleert
10. [ ] Route-Cache geleert
11. [ ] Getestet

## 🎓 Wichtige Konzepte

### Team-basierte Daten

**IMMER** Team-Filterung verwenden:
```php
$user = Auth::user();
$team = $user->currentTeam;
$data = Model::where('team_id', $team->id)->get();
```

### UUIDs

**IMMER** UUIDs für Models verwenden:
```php
use Symfony\Component\Uid\UuidV7;

protected static function booted(): void {
    static::creating(function ($model) {
        if (empty($model->uuid)) {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());
            $model->uuid = $uuid;
        }
    });
}
```

### Activity Logging

**IMMER** `LogsActivity` Trait verwenden:
```php
use Platform\ActivityLog\Traits\LogsActivity;

class Model extends Model {
    use LogsActivity;
    // ...
}
```

## 🚀 Nächste Schritte

Nach dem Erstellen des Basis-Moduls:

1. **Models hinzufügen** - In `src/Models/`
2. **Migrationen erstellen** - In `database/migrations/`
3. **Livewire Components erweitern** - Index, Show, Create, Edit
4. **Routes erweitern** - Für neue Views
5. **Policies erstellen** - Für Authorization
6. **Tests schreiben** - Für wichtige Funktionen

## 💡 Tipps für LLMs

1. **Folge den Patterns** - Dieses Template zeigt bewährte Patterns
2. **Konsistenz** - Halte dich an die Namenskonventionen
3. **Dokumentation** - Kommentiere wichtige Stellen
4. **Beispiele** - Sieh dir HCM/Planner für komplexere Beispiele an
5. **Testen** - Teste nach jeder Änderung
