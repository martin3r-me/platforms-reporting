# Platform Reporting

Dieses Modul dient als **Template und Startpunkt** für neue Module in der Platform.

## 📋 Übersicht

Dieses Template zeigt die **minimale Struktur** eines Platform-Moduls:
- ✅ Service Provider mit Modul-Registrierung
- ✅ Config-Datei mit Navigation und Sidebar
- ✅ Route (Dashboard)
- ✅ Livewire Components (Dashboard, Sidebar)
- ✅ Leeres, aber funktionsfähiges Dashboard mit beiden innenliegenden Sidebars (links & rechts)
- ✅ **nx-Design-System** (Notion-Look) — Referenz-Setup für neue Module (siehe `DESIGN.md`)
- ✅ Vollständige Dokumentation für LLMs

> **Design:** Dieses Modul ist das **Referenz-/Beispielmodul für nx**. Rahmen bleiben `x-ui-*`
> (bereits auf nx-Tokens), aller Inhalt baut mit `x-nx-*` + `var(--nx-*)`. Details in
> [`DESIGN.md`](DESIGN.md) · Kanon: `platform/ui-tailwind/docs/nx-design-system.md`.

Das Dashboard startet **leer** (Platzhalter-Content), damit ein neues Modul
sauber bei null beginnt — die Page-Shell inkl. auf-/zuklappbarer Sidebars ist
aber bereits verdrahtet.

## 🚀 Schnellstart

### 1. Modul kopieren und umbenennen

```bash
# Kopiere das Template-Modul
cp -r platform/modules/reporting platform/modules/dein-modul-name

# Gehe in das neue Modul
cd platform/modules/dein-modul-name
```

### 2. Dateien umbenennen und anpassen

**WICHTIG:** Ersetze in ALLEN Dateien:
- `Reporting` → `DeinModulName` (Namespace)
- `reporting` → `dein-modul-name` (Verzeichnisname, Route-Prefix)
- `reporting` → `dein_modul_name` (Config-Key, Tabellennamen)

**Dateien die angepasst werden müssen:**
- `composer.json` - Name, Namespace, Provider
- `config/reporting.php` → `config/dein-modul-name.php`
- `src/ReportingServiceProvider.php` → `src/DeinModulNameServiceProvider.php`
- Alle PHP-Dateien: Namespace ändern
- Alle Blade-Dateien: `reporting::` → `dein-modul-name::`
- Routes: `reporting` → `dein-modul-name`

### 3. Composer registrieren

Füge das Modul zur Hauptanwendung hinzu:

**In `composer.json` der Hauptanwendung:**
```json
{
  "require": {
    "martin3r/platform-dein-modul-name": "dev-main"
  },
  "repositories": [
    {
      "type": "path",
      "url": "../platform/modules/dein-modul-name"
    }
  ]
}
```

Dann:
```bash
composer update
```

### 4. Config publizieren (optional)

```bash
php artisan vendor:publish --tag=config --provider="Platform\DeinModulName\DeinModulNameServiceProvider"
```

## 📁 Struktur

```
reporting/
├── composer.json              # Package-Definition
├── config/
│   └── reporting.php    # Modul-Konfiguration
├── database/
│   └── migrations/            # Migrationen (optional)
├── resources/
│   └── views/
│       └── livewire/
│           ├── dashboard.blade.php    # Dashboard-View (leer, mit beiden Sidebars)
│           ├── showcase.blade.php     # nx-Showcase (alle x-nx-* Komponenten live)
│           └── sidebar.blade.php      # Sidebar-View
├── routes/
│   └── web.php                # Web-Routes
├── src/
│   ├── ReportingServiceProvider.php  # Service Provider
│   └── Livewire/
│       ├── Dashboard.php       # Dashboard Component
│       ├── Showcase.php        # nx-Komponenten-Showcase (Design-Referenz)
│       └── Sidebar.php        # Sidebar Component
├── DESIGN.md                   # nx-Design-Brief (verbindlich für den Content)
├── LLM_GUIDE.md                # Patterns & Komponenten für LLMs
└── README.md                   # Diese Datei
```

## 🔧 Wichtige Komponenten

### Service Provider

Der `ReportingServiceProvider` ist das Herzstück des Moduls:

1. **register()**: Config wird hier geladen (Laravel Best Practice)
2. **boot()**: 
   - Modul wird bei PlatformCore registriert
   - Routes werden geladen (nur wenn Modul aktiv)
   - Views und Livewire-Komponenten werden registriert

### Config-Datei

Die Config (`config/reporting.php`) definiert:
- **routing**: Route-Modus (path/subdomain) und Prefix
- **navigation**: Hauptnavigation (Icon, Route, Order)
- **sidebar**: Sidebar-Struktur für das Modul

### Routes

- `/reporting` → Dashboard
- `/reporting/showcase` → nx-Showcase (lebendes Verzeichnis aller `x-nx-*` Komponenten)

### Livewire Components

- **Dashboard**: Hauptübersicht (leer, mit linker & rechter Sidebar)
- **Showcase**: Lebendes Verzeichnis aller `x-nx-*` Komponenten (Design-Referenz)
- **Sidebar**: Modul-spezifische Sidebar

## 📝 Anpassungen für dein Modul

### 1. Models hinzufügen

Erstelle Models in `src/Models/`:
```php
<?php
namespace Platform\DeinModulName\Models;

use Illuminate\Database\Eloquent\Model;
use Platform\ActivityLog\Traits\LogsActivity;

class DeinModulNameEntity extends Model
{
    use LogsActivity;
    
    protected $table = 'dein_modul_name_entities';
    // ...
}
```

### 2. Migrationen erstellen

```bash
php artisan make:migration create_dein_modul_name_entities_table
```

### 3. Livewire Components erweitern

Füge neue Components in `src/Livewire/` hinzu:
- Index-Views für Listen
- Create/Edit Modals
- Show-Views für Details

### 4. Routes erweitern

In `routes/web.php`:
```php
Route::get('/entities', Entity\Index::class)->name('dein-modul-name.entities.index');
```

## 🎯 Best Practices

1. **nx-Design-System**: Inhalt mit `x-nx-*` + `var(--nx-*)` bauen, Rahmen mit `x-ui-page*`.
   Keine `var(--ui-*)`/ENV-Farben, keine Gradients/Schatten (siehe `DESIGN.md`)
2. **Immer Team-basiert**: Nutze `$user->currentTeam->id` für Team-Filterung
3. **Activity Logging**: Nutze `LogsActivity` Trait für Models
4. **UUIDs**: Verwende UUIDs für alle Models (UuidV7)
5. **Policies**: Erstelle Policies für Authorization
6. **Sidebars**: Beide Sidebars (links & rechts) in allen Views
7. **Modals**: `x-nx-modal`, immer innerhalb von `<x-ui-page>` platzieren

## 🤖 Für LLMs

Dieses Template ist so strukturiert, dass LLMs es verstehen können:

- **Klare Namenskonventionen**: Alles folgt dem Muster `{modul-name}`
- **Ausführliche Kommentare**: Alle wichtigen Stellen sind dokumentiert
- **Konsistente Struktur**: Gleiche Struktur wie andere Module (HCM, Planner)
- **Beispiel**: Das leere Dashboard zeigt die Page-Shell mit beiden Sidebars

**Wichtige Patterns:**
- Service Provider Pattern (wie in HCM/Planner)
- Livewire Component Pattern
- Route Registration Pattern
- Sidebar Pattern (links & rechts)

## 📚 Weitere Ressourcen

- Siehe `platform/modules/hcm` für komplexere Beispiele
- Siehe `platform/modules/planner` für Modals und erweiterte Features
- Siehe `platform/core/src/PlatformCore.php` für Modul-Registrierung

## ✅ Checkliste für neues Modul

- [ ] Modul kopiert und umbenannt
- [ ] Alle Namespaces angepasst
- [ ] Composer.json angepasst
- [ ] Config-Datei angepasst
- [ ] Routes angepasst
- [ ] Service Provider angepasst
- [ ] Views angepasst
- [ ] In Hauptanwendung registriert
- [ ] `composer dump-autoload` ausgeführt
- [ ] Config publiziert (optional)
- [ ] Getestet

## 🐛 Troubleshooting

**Routen funktionieren nicht:**
- Config publiziert? → `php artisan vendor:publish --tag=config`
- Config-Cache geleert? → `php artisan config:clear`
- Route-Cache geleert? → `php artisan route:clear`

**Modul erscheint nicht in Navigation:**
- Modul in Datenbank registriert? → Prüfe `modules` Tabelle
- Config korrekt? → Prüfe `config/dein-modul-name.php`

**Livewire Components nicht gefunden:**
- Service Provider registriert? → Prüfe `composer.json`
- `composer dump-autoload` ausgeführt?
