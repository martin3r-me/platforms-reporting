# Module Template — Design Brief

## System: nx (plattformweit, Notion-inspiriert)

Dieses Modul ist das **Referenz-/Beispielmodul** für das nx-Design-System. Neue Module
werden von hier kopiert und übernehmen damit automatisch den richtigen Look.

**nx** ist das feste, env-**un**abhängige Design-System der Platform. Ziel: jedes Modul sieht
gleich aus, unabhängig von Kunden-/ENV-Tokens. Es löst schrittweise das alte
`x-ui-*` / `--ui-*`-System ab — **additiv**, Modul für Modul.

> **Kanonische Referenz:** `platform/ui-tailwind/docs/nx-design-system.md`
> (vollständige Token- & Komponentenliste). Diese Datei ist die Kurzfassung fürs Template.

---

## Die zwei Ebenen

| Ebene | Komponenten | Regel |
|-------|-------------|-------|
| **Shell / Rahmen** („Eimer 1") | `x-ui-page`, `x-ui-page-navbar`, `x-ui-page-actionbar`, `x-ui-page-container`, `x-ui-page-sidebar`, `x-ui-sidebar-list`, `x-ui-sidebar-item` | Bleiben aus Kompatibilität `x-ui-*`, sind aber bereits auf nx-Tokens. Unverändert übernehmen. |
| **Inhalt** | `x-nx-*` (card, button, badge, table, modal, stat, empty, section, list-item, callout, dropdown, form/*, …) | **Alles im Content-Bereich baut mit `x-nx-*` + `var(--nx-*)`.** |

---

## Prinzipien

1. **Env-unabhängige Tokens.** Nur `var(--nx-*)` (definiert in `ui-styles.blade.php`, `:root`).
   **Niemals** `var(--ui-*)`, keine ENV-Farben, keine hart kodierten Hex-Werte.
2. **Chrome neutral, Inhalt trägt Farbe.** Rahmen/Sidebars sind grau/weiß. Farbe lebt *dezent*
   und **bedeutungstragend** im Inhalt: Status → Badge, Hinweis → Callout, KPI → Stat-Icon,
   Timing → Farbe. Nie Deko.
3. **Ruhig statt laut.** Hairlines statt Rahmen, **keine Schatten** (außer Popover/Modal),
   **keine Gradients**, near-black als Akzent, großzügige Luft, „quiet until hover".
4. **Reines Tailwind** mit `bg-[color:var(--nx-…)]`. Neue Klassen → `npm run build`;
   reine Token-/Inline-Änderungen → `php artisan view:clear`.

## Tokens (Kurzreferenz)

Fläche `--nx-bg` (Chrome) · `--nx-surface` (Cards) · Text `--nx-text` / `--nx-muted` / `--nx-faint` ·
Linien `--nx-line` / `--nx-line-strong` · Hover `--nx-hover` / `--nx-active` ·
Akzent `--nx-accent` (near-black) / `--nx-on-accent` ·
Semantik `--nx-success` / `--nx-danger` / `--nx-warning` / `--nx-info` ·
Radius `--nx-radius-sm|--nx-radius|--nx-radius-lg`.

## Verbindliche Konventionen

- **Actionbar-Aktionen:** genau **1 → sichtbarer `x-nx-button`**, **≥2 → ein `x-nx-dropdown`**.
  Keine Content-/Bulk-Aktionen rechts.
- **Tabelle vs. Liste vs. Karte — die Datenform entscheidet, nicht der Geschmack:**
  - **Tabelle** (`x-nx-table`) = Register/Ledger: gleichförmige Datensätze, spaltenweise
    vergleichen/sortieren/filtern; skalare Felder. → Buchungen, Umsatz, Export.
  - **Liste** (`x-nx-card flush` + `x-nx-list-item`) = Katalog/Entitäten mit Identität
    (Icon/Bild, Titel, Badges); erkennen & handeln. → Artikel, Venues.
  - **Kartenraster** = nur wenn das Bild der Hauptinhalt ist (Galerie).
  - Schnelltest: „Nach Spalte sortieren & Zeilen vergleichen?" → Tabelle. „Identität je
    Element, browse zum Wiedererkennen?" → Liste/Karte.
- **Gemeinsame Grammatik:** rahmenlos auf Weiß, Hairline-Zeilen, **Zeile klickbar = Hauptaktion**;
  Sekundäraktionen als Hover-Icons mit `wire:click.stop`.
- **Modal-Größen:** `sm` Bestätigung/1 Feld · `md` Default · `lg` mehrspaltig · `xl` große Tools.
- **Container-Breite:** `width="contained"` für Dashboards/Listen/Formulare; `full` für
  Kanban/Canvas/breite Tabellen.

## Regeln (Do / Don't)

✅ **Do**
- Inhalt mit `x-nx-*` bauen, Rahmen mit `x-ui-page*` lassen.
- Nur `var(--nx-*)` Tokens.
- Farbe nur bedeutungstragend.
- Neue Komponente fehlt? → in `ui-tailwind` unter `components-nx/` anlegen (siehe nx-Doku §6),
  nicht ad-hoc im Modul nachbauen.

🚫 **Don't**
- Keine `var(--ui-*)`, keine ENV-/Hex-Farben.
- Keine Gradients, keine Schatten (außer Popover/Modal), kein Frosted-Glass.
- Keine Ad-hoc-Breiten/-Buttons, wenn eine nx-Komponente existiert.

## Wo sehe ich das in Aktion?

- **`/reporting/showcase`** (`livewire/showcase.blade.php`) — **lebendes Verzeichnis: JEDE
  `x-nx-*` Komponente einmal live gerendert**, gruppiert mit „wann nutzen"-Info. Zugleich die
  Copy-Paste-Referenz. Beim Hinzufügen einer Komponente hier einen Block ergänzen.
- `resources/views/livewire/dashboard.blade.php` — Page-Shell + `x-nx-empty` + auskommentierter
  Referenzblock (stat-grid, section, table, list, callout, modal).
- `resources/views/livewire/sidebar.blade.php` — Sidebar auf nx-Tokens.
- Weitere migrierte Module als Vorbild: `okr`, `home`, `planner`.
