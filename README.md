# Platform Reporting

Das **Reporting-Modul** trägt die **Verbalisierungs-/Reporting-Engine** der Platform —
Feeds, Kanäle, Rezepte, Baselines und die MCP-Tools dazu. Es wurde aus dem Core
herausgelöst; der Core hält nur noch den Facade-Contract und das Fallback.

> Scaffolding-Herkunft: Das Modul wurde aus dem nx-Template-Modul kopiert. Daher liegen
> hier noch `DESIGN.md`, die `Showcase`-Komponente und `/reporting/showcase` als
> nx-Design-Referenz. Der fachliche Kern ist `src/Verbalization/` + `src/Tools/`.

## Architektur: Facade + Binding-Override

Der Core darf **nie** am Reporting-Modul hängen (Facade-Prinzip: Module hängen an Core,
nie umgekehrt). Die Umschaltung läuft deshalb über ein Binding-Override:

| Ebene | Ort | Rolle |
|-------|-----|-------|
| **Contract** | `Platform\Core\Verbalization\Contracts\ReportEngine` (Core) | Facade, an der Produzenten-Module (planner, org, …) hängen. Bleibt dauerhaft im Core. |
| **Fallback** | `CoreServiceProvider::register()` → `CoreVerbalizationEngine` (Core) | Greift, wenn reporting **nicht** installiert ist. |
| **Override** | `ReportingServiceProvider::boot()` → `ReportingEngine` (dieses Modul) | Überschreibt das Fallback **deterministisch** (boot läuft nach allen register). |

**Ergebnis:** reporting installiert → Modul-Engine fährt die Verbalisierung; reporting
fehlt → Core-Fallback bleibt → kein Bruch. Bewusst **ohne** Core-Change.

## Der Schnitt: was hier liegt, was im Core bleibt

Bewusst ein Layer-Schnitt, keine Voll-Kopie:

**In diesem Modul (das „Tun"):**
- `src/Verbalization/ReportingEngine.php` — die Modul-Engine (implementiert `ReportEngine`)
- `Verbalization/Verbalizer.php`, `Channel/*`-Renderer (Web, RSS, Obsidian), `Feed/*`
  (Service, Atom-Renderer, Controller), `Recipe/RecipeResolver`, `Baseline/BaselineService`
- `src/Tools/*` — MCP-Tools für Channels/Feeds/Recipes/Outputs
- `src/Livewire/Factory.php` — der Baukasten (ersetzt `core.verbalization.factory`)

**Im Core geblieben (Vokabular + Persistenz, vom Modul konsumiert):**
- Contract `ReportEngine`
- Value-Objects: `Subject`, `Fact`, `Claim`, `StyleProfile`, `GuardRails`,
  `VerbalizationResult`, `Template/*`, `Enums/*`, `CollectionRecipe`, `Pulse/*` …
- Eloquent-Models: `VerbalizationFeed`, `VerbalizationChannel`, `VerbalizationOutput`,
  `VerbalizationRecipe`, `VerbalizationBaseline`

`Platform\Reporting` → `Platform\Core` ist erlaubt und gewollt; die Gegenrichtung nicht.

## Status & offener Rest (der „Contract"-Schritt)

Aktuell existieren die kopierten „Tun"-Klassen **doppelt** — im Core (für das Fallback)
und hier. Sobald reporting in **jeder** Instanz aktiv ist, wird aus dem Core entfernt:

1. `CoreVerbalizationEngine`
2. das Fallback-Binding in `CoreServiceProvider`
3. die Core-seitige Verbalization-Tool-Registrierung
4. die `core.verbalization.factory`-Route

Bis dahin gilt: **Core-Kopien sind eingefroren** — fachliche Änderungen an Verbalizer/
Channel/Feed/Recipe/Baseline passieren hier im Modul, nicht in beiden Bäumen.

## Routes

- `/reporting` → Dashboard
- `/reporting/factory` → Verbalization-Baukasten (ersetzt `core.verbalization.factory`)
- `/reporting/showcase` → nx-Design-Referenz (alle `x-nx-*` Komponenten live)
- Feed-Ausgabe: siehe `Verbalization/Feed/FeedController`

## Design

Inhalt baut mit `x-nx-*` + `var(--nx-*)`, Rahmen mit `x-ui-page*`. Details in
[`DESIGN.md`](DESIGN.md) · Kanon: `platform/ui-tailwind/docs/nx-design-system.md`.
