<?php

namespace Platform\Reporting\Reports;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Ausschnitt eines Berichts — Organisationseinheiten suchen/benennen und
 * Subjects lesbar machen. organization ist optional: ohne das Modul gibt es
 * keine Einheiten, der Baukasten zeigt das an statt zu brechen.
 */
class ReportScope
{
    protected const ENTITY_CLASS = \Platform\Organization\Models\OrganizationEntity::class;

    /** Subject-Type → Morph-Alias, wo sie auseinanderlaufen (wie FeedService::linkableTypeAliases). */
    protected const MORPH_ALIASES = [
        'planner_project' => 'project',
    ];

    public function organizationAvailable(): bool
    {
        return class_exists(self::ENTITY_CLASS);
    }

    /**
     * @return array<int, array{id:int,name:string,path:string}>
     */
    public function searchEntities(?int $teamId, string $term, int $limit = 8): array
    {
        if (! $this->organizationAvailable()) {
            return [];
        }

        $q = (self::ENTITY_CLASS)::query()
            ->with('parent:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->limit($limit);
        if ($teamId) {
            $q->where('team_id', $teamId);
        }
        if (trim($term) !== '') {
            $q->where('name', 'like', '%' . trim($term) . '%');
        } else {
            // Ohne Suchbegriff: die obersten Ebenen als Einstieg.
            $q->whereNull('parent_entity_id');
        }

        return $q->get()->map(fn ($e) => [
            'id' => (int) $e->id,
            'name' => (string) $e->name,
            'path' => $e->parent ? (string) $e->parent->name : '',
        ])->all();
    }

    public function entityName(?int $id): ?string
    {
        if (! $id || ! $this->organizationAvailable()) {
            return null;
        }

        return (self::ENTITY_CLASS)::query()->whereKey($id)->value('name');
    }

    /** Gehört die Einheit zum Team? (Auswahl nur aus dem eigenen Team.) */
    public function entityInTeam(?int $id, ?int $teamId): bool
    {
        if (! $id || ! $this->organizationAvailable()) {
            return false;
        }

        return (self::ENTITY_CLASS)::query()
            ->whereKey($id)
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->exists();
    }

    /** @return array<int, string> id → Name, in einem Rutsch */
    public function entityNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === [] || ! $this->organizationAvailable()) {
            return [];
        }

        return (self::ENTITY_CLASS)::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /** Lesbarer Name eines Subjects (Einheit, Projekt, …) — Fallback „typ #id". */
    public function subjectLabel(string $type, string|int $id, SubjectCatalog $catalog): string
    {
        if ($catalog->isEntityType($type)) {
            return $this->entityName((int) $id) ?? ('Einheit #' . $id);
        }

        $alias = self::MORPH_ALIASES[$type] ?? $type;
        $class = Relation::getMorphedModel($alias);
        if ($class && class_exists($class)) {
            try {
                $model = $class::query()->find($id);
                $name = $model?->name ?? $model?->title ?? null;
                if ($name) {
                    return (string) $name;
                }
            } catch (\Throwable $e) {
                // fällt auf den Fallback
            }
        }

        return $catalog->describe($type)['label'] . ' #' . $id;
    }
}
