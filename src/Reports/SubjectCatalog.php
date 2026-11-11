<?php

namespace Platform\Reporting\Reports;

use Platform\Core\Verbalization\SubjectCollector\SubjectCollectorRegistry;

/**
 * Was kann ein Bericht beschreiben? Übersetzt die registrierten Subject-Collectors
 * in die Sprache des Baukastens (Titel, Erklärung, Icon, Art des Ausschnitts).
 *
 * Zwei Arten:
 *   entity — das Subject IST eine Organisationseinheit (Puls, Signale) → genau eine Einheit wählen
 *   linked — Objekte, die an Einheiten hängen (Projekte, …) → Einheit (+ Teilbaum) als Ausschnitt
 *
 * Unbekannte Collectors erscheinen trotzdem — mit ihrem technischen Namen und als „linked".
 */
class SubjectCatalog
{
    /** Bekannte Typen; Module, die hier fehlen, bekommen den Fallback. */
    protected const KNOWN = [
        'entity_pulse' => [
            'label' => 'Puls einer Einheit',
            'description' => 'Was sich an einer Organisationseinheit bewegt: Projekte, Signale, Kennzahlen aller angehängten Module.',
            'icon' => 'heroicon-o-heart',
            'kind' => 'entity',
        ],
        'organization_signals' => [
            'label' => 'Signale',
            'description' => 'Die VSM-Signallage einer Einheit: was neu ist, was eskaliert, was erledigt wurde.',
            'icon' => 'heroicon-o-bell-alert',
            'kind' => 'entity',
        ],
        'planner_project' => [
            'label' => 'Projekte',
            'description' => 'Ein Absatz je Projekt — Stand, Bewegung, wer am Zug ist.',
            'icon' => 'heroicon-o-folder',
            'kind' => 'linked',
        ],
    ];

    /**
     * @return array<string, array{type:string,label:string,description:string,icon:string,kind:string}>
     */
    public function all(): array
    {
        try {
            $types = app(SubjectCollectorRegistry::class)->registered();
        } catch (\Throwable $e) {
            $types = [];
        }

        $out = [];
        foreach ($types as $type) {
            $out[$type] = $this->describe($type);
        }

        // Bekannte zuerst, in fester Reihenfolge; der Rest alphabetisch dahinter.
        uksort($out, function ($a, $b) {
            $order = array_keys(self::KNOWN);
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);
            if ($ia !== false || $ib !== false) {
                return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
            }

            return strcmp($a, $b);
        });

        return $out;
    }

    public function describe(?string $type): array
    {
        $type = (string) $type;
        $known = self::KNOWN[$type] ?? null;

        return [
            'type' => $type,
            'label' => $known['label'] ?? ucfirst(str_replace('_', ' ', $type)),
            'description' => $known['description'] ?? 'Bericht über „' . $type . '".',
            'icon' => $known['icon'] ?? 'heroicon-o-document-text',
            'kind' => $known['kind'] ?? 'linked',
        ];
    }

    public function isEntityType(?string $type): bool
    {
        return $this->describe($type)['kind'] === 'entity';
    }
}
