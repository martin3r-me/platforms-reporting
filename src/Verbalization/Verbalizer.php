<?php

namespace Platform\Reporting\Verbalization;

use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\Fact;
use Platform\Core\Verbalization\StyleProfile;
use Platform\Core\Verbalization\GuardRails;
use Platform\Core\Verbalization\Freshness;
use Platform\Core\Verbalization\VerbalizationResult;

use Platform\Core\Contracts\LLMProviderContract;
use Platform\Core\Services\LLMProviderRegistry;
use Platform\Core\Verbalization\Enums\FactPriority;
use Platform\Core\Verbalization\Recipe\CollectionRecipe;
use Platform\Core\Verbalization\Template\NarrativeTemplate;
use Platform\Core\Verbalization\Template\TemplateRegistry;

/**
 * Verbalizer — Orchestriert die 6 Bausteine zu Prosa.
 *
 *  1) Subject ist schon befuellt (vom Sammler).
 *  2) Priorisierer: implizit in Subject->facts/edges via priority/weight.
 *  3) Gruppierer: V1 nicht implementiert.
 *  4) Erzaehlvorlage: Template aus Registry, sonst generisch.
 *  5) Faktenbasis: Template::renderFactSheet — deterministische Wahrheit.
 *  6) LLM-Politur mit Leine: kleiner Auftrag, harte Guards.
 *
 * Die Klasse selbst kennt keine Domaene, keine DB, keine Module.
 */
class Verbalizer
{
    public function __construct(
        protected LLMProviderRegistry $providers,
        protected TemplateRegistry $templates,
    ) {}

    public function verbalize(
        Subject $subject,
        ?StyleProfile $style = null,
        ?GuardRails $rails = null,
        ?string $providerKey = null,
        ?string $modelOverride = null,
        ?CollectionRecipe $recipe = null,
    ): VerbalizationResult {
        $style ??= StyleProfile::formal();
        $rails ??= new GuardRails();

        // Recipe darf Style + Guards anreichern (Recipe-Werte ueberschreiben Defaults,
        // aber NICHT explizite Caller-Werte — wenn Caller bewusst etwas gesetzt hat,
        // bleibt das. Pragmatik: nur Felder mergen, die Recipe gesetzt hat.)
        if ($recipe) {
            $style = $this->mergeRecipeStyle($style, $recipe);
            $rails = $this->mergeRecipeGuards($rails, $recipe);
        }

        $llm = $this->resolveProvider($providerKey, $recipe);
        $effectiveModel = $modelOverride ?? $recipe?->llmModel();

        // Recipe-Nature-Filter: der Sammler liefert alles, was er hat. Erst hier
        // filtern wir auf die von der Recipe erlaubten Fact-Naturen (state/movement/
        // derivation). Damit werden Rezepte wie change_ticker (nur movement+derivation)
        // oder state_only (nur state) zu reinen Konfigurations-Umschaltungen.
        $subjectForRender = $recipe
            ? $subject->withFilteredFacts(fn ($f) => $recipe->includesNature($f->nature))
            : $subject;

        $template = $this->templates->resolve($subject->type);
        $factSheet = $template
            ? $template->renderFactSheet($subjectForRender)
            : $this->renderGenericFactSheet($subjectForRender);

        $systemPrompt = $this->buildSystemPrompt($style, $rails);
        $userPrompt = $this->buildUserPrompt($factSheet, $style);

        $options = [
            'system' => $systemPrompt,
            'temperature' => 0.3,
            'max_tokens' => 1024,
        ];
        if ($effectiveModel) {
            $options['model'] = $effectiveModel;
        }

        $response = $llm->chat(
            messages: [
                ['role' => 'user', 'content' => $userPrompt],
            ],
            options: $options,
        );

        return new VerbalizationResult(
            prose: trim($response['content'] ?? ''),
            factSheet: $factSheet,
            model: $response['model'] ?? 'unknown',
            usage: $response['usage'] ?? [],
            meta: [
                'subject_type' => $subject->type,
                'subject_id' => $subject->id,
                'template_used' => $template ? get_class($template) : 'generic',
                'provider' => $llm->getName(),
            ],
        );
    }

    protected function mergeRecipeStyle(StyleProfile $base, CollectionRecipe $recipe): StyleProfile
    {
        $s = $recipe->style;
        if (empty($s)) {
            return $base;
        }
        return new StyleProfile(
            address: $s['address'] ?? $base->address,
            tone: $s['tone'] ?? $base->tone,
            rhythm: $s['rhythm'] ?? $base->rhythm,
            perspective: $s['perspective'] ?? $base->perspective,
            semanticLayer: $base->semanticLayer, // Layer kommt vom Caller, nicht aus Recipe
            extraInstruction: $s['extra_instruction'] ?? $base->extraInstruction,
        );
    }

    protected function mergeRecipeGuards(GuardRails $base, CollectionRecipe $recipe): GuardRails
    {
        $g = $recipe->guards;
        if (empty($g)) {
            return $base;
        }
        return new GuardRails(
            factsOnly: (bool) ($g['factsOnly'] ?? $base->factsOnly),
            admitGaps: (bool) ($g['admitGaps'] ?? $base->admitGaps),
            noSpeculation: (bool) ($g['noSpeculation'] ?? $base->noSpeculation),
            consistent: (bool) ($g['consistent'] ?? $base->consistent),
        );
    }

    /**
     * Aufloesungs-Reihenfolge fuer den LLM-Provider:
     *   1) Feed-/Caller-Override ($key)
     *   2) Recipe-Praeferenz ($recipe->llmProvider())
     *   3) Config-Default (verbalization.default_provider)
     *   4) Registry-First-Available
     *
     * Feed und Recipe muessen — wenn gesetzt — auch verfuegbar sein; sonst fliegt
     * der Provider explizit als Fehler durch (keine stillen Fallbacks, sonst
     * versendet man Prosa vom "falschen" Modell ohne es zu merken).
     */
    protected function resolveProvider(?string $key, ?CollectionRecipe $recipe = null): LLMProviderContract
    {
        if ($key) {
            return $this->requireProvider($key, 'Feed');
        }

        $recipeProvider = $recipe?->llmProvider();
        if ($recipeProvider) {
            return $this->requireProvider($recipeProvider, "Recipe '{$recipe->key}'");
        }

        $configured = config('verbalization.default_provider');
        if ($configured) {
            $p = $this->providers->get($configured);
            if ($p && $p->isAvailable()) {
                return $p;
            }
        }

        return $this->providers->getDefaultProvider()
            ?? throw new \RuntimeException('Verbalizer: kein LLM-Provider verfuegbar.');
    }

    protected function requireProvider(string $key, string $source): LLMProviderContract
    {
        $p = $this->providers->get($key);
        if (! $p) {
            throw new \RuntimeException("Verbalizer: Provider '{$key}' ({$source}) ist nicht registriert.");
        }
        if (! $p->isAvailable()) {
            throw new \RuntimeException("Verbalizer: Provider '{$key}' ({$source}) ist nicht verfuegbar (API-Key fehlt?).");
        }
        return $p;
    }

    protected function buildSystemPrompt(StyleProfile $style, GuardRails $rails): string
    {
        $parts = [];

        if ($style->semanticLayer) {
            $parts[] = $style->semanticLayer;
            $parts[] = '';
        }

        $parts[] = 'Du bist ein praeziser Verbalisierer. Deine Aufgabe ist NICHT zu denken oder zu erfinden, sondern eine vorgegebene Faktenliste in fluessige Prosa zu giessen.';
        $parts[] = '';
        $parts[] = 'Harte Regeln:';
        $parts[] = $rails->asPromptRules();
        $parts[] = '';
        $parts[] = 'Format:';
        $parts[] = '- Antwort ist lesbares Markdown.';
        $parts[] = '- **Absaetze trennen Themen.** Wenn die Faktenbasis verschiedene Themenbloecke hat (Status, Beteiligte, Termine, Daten-Grundlage), gehoeren die in EIGENE Absaetze, getrennt durch Leerzeilen. KEINE Wand-aus-Text.';
        $parts[] = '- Max. 3-4 Saetze pro Absatz. Wenn ein Absatz zu lang wird, splitten.';
        $parts[] = '- Headings (##/###) NUR setzen, wenn mehrere echt unterscheidbare Sektionen vorliegen — niemals einfach die Headings aus der Faktenbasis kopieren.';
        $parts[] = '- **fett** fuer Schluesselzahlen (Health-Score, Prozent-Fortschritte, kritische Counts).';
        $parts[] = '- Listen nur wenn 3 oder mehr gleichartige Punkte aneinandergereiht waeren; bei 1-2 Punkten Fliesstext.';
        $parts[] = '- Keine leeren Zierfloskeln ("Im Rahmen von", "In Bezug auf", "Hinsichtlich"). Aktive, kurze Saetze.';
        $parts[] = '- Wenn die extra_instruction "kein Markdown" oder "Telegramm" verlangt, gilt das — Markdown deaktivieren, reines Klartext.';
        $parts[] = '';
        $parts[] = 'Stil:';
        $parts[] = '- Anrede: ' . ($style->address === 'du' ? 'Du-Form' : 'Sie-Form / unpersoenlich');
        $parts[] = '- Tonalitaet: ' . match ($style->tone) {
            'formal' => 'sachlich, klar, professionell',
            'collegial' => 'kollegial, direkt, intern',
            'evocative' => 'evokativ, bildhaft (Atmosphaere transportieren, aber nur durch wahre Fakten)',
            default => 'sachlich',
        };
        $parts[] = '- Satzrhythmus: ' . ($style->rhythm === 'short' ? 'kurze Saetze' : 'fluessige, verknuepfte Saetze');

        if ($style->extraInstruction) {
            $parts[] = '';
            $parts[] = 'Zusatz:';
            $parts[] = $style->extraInstruction;
        }

        return implode("\n", $parts);
    }

    protected function buildUserPrompt(string $factSheet, StyleProfile $style): string
    {
        return <<<TXT
Hier ist die Faktenbasis. Wandle sie in fluessige Prosa um — in der Reihenfolge, die die Faktenbasis vorgibt. KEIN Fakt darf dazu- oder weggelassen werden. Antworte NUR mit der Prosa, ohne Vorrede oder Meta-Kommentar.

----- FAKTENBASIS -----
{$factSheet}
----- ENDE FAKTENBASIS -----
TXT;
    }

    /**
     * Fallback-Faktenbasis wenn kein typ-spezifisches Template registriert ist.
     * Listet Identitaet, Facts (nach Priority sortiert), Edges (nach Weight), Freshness.
     */
    protected function renderGenericFactSheet(Subject $subject): string
    {
        $lines = [];

        $lines[] = 'Knoten: ' . $subject->identity->primaryName;
        $lines[] = 'Typ: ' . $subject->type;
        $lines[] = '';

        $sortedFacts = collect($subject->facts)
            ->sortBy(fn ($f) => match ($f->priority) {
                FactPriority::CORE => 0,
                FactPriority::QUALIFYING => 1,
                FactPriority::CONTEXT => 2,
            });

        if ($sortedFacts->isNotEmpty()) {
            $lines[] = 'Fakten (Reihenfolge = Wichtigkeit):';
            foreach ($sortedFacts as $f) {
                $lines[] = '  - [' . $f->priority->value . '] ' . $f->text;
            }
            $lines[] = '';
        }

        $sortedEdges = collect($subject->edges)
            ->sortBy(fn ($e) => match ($e->weight) {
                FactPriority::CORE => 0,
                FactPriority::QUALIFYING => 1,
                FactPriority::CONTEXT => 2,
            });

        if ($sortedEdges->isNotEmpty()) {
            $lines[] = 'Beziehungen:';
            foreach ($sortedEdges as $e) {
                $claimMarker = $e->claim->type->value === 'system_verified'
                    ? ''
                    : ' [' . $e->claim->type->value . '/' . $e->claim->level->value . ($e->claim->sourceName ? ', ' . $e->claim->sourceName : '') . ']';
                $lines[] = '  - ' . $e->relation . ' -> ' . $e->targetLabel . $claimMarker;
            }
            $lines[] = '';
        }

        $lines[] = 'Frische: ' . $subject->freshness->source->value
            . ' (Stand: ' . $subject->freshness->asOf->format('Y-m-d H:i') . ')';

        return implode("\n", $lines);
    }
}
