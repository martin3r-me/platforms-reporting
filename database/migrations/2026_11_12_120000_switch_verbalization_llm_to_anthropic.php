<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Berichte laufen über die Claude-API, nicht über OpenAI: Recipes und Feeds, die
 * OpenAI fest eingestellt haben, auf anthropic umstellen. Das Modell fällt weg —
 * dann gilt das Default-Modell des Providers (ai.anthropic.inference_model).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('verbalization_recipes')) {
            DB::table('verbalization_recipes')->whereNotNull('llm')->orderBy('id')->each(function ($row) {
                $llm = json_decode((string) $row->llm, true);
                if (! is_array($llm) || ! $this->isOpenAi($llm['provider'] ?? null, $llm['model'] ?? null)) {
                    return;
                }
                DB::table('verbalization_recipes')->where('id', $row->id)
                    ->update(['llm' => json_encode(['provider' => 'anthropic'])]);
            });
        }

        if (Schema::hasTable('verbalization_feeds')) {
            DB::table('verbalization_feeds')->orderBy('id')->each(function ($row) {
                if (! $this->isOpenAi($row->llm_provider, $row->llm_model)) {
                    return;
                }
                DB::table('verbalization_feeds')->where('id', $row->id)
                    ->update(['llm_provider' => 'anthropic', 'llm_model' => null]);
            });
        }
    }

    public function down(): void
    {
        // Bewusst nicht zurück auf OpenAI.
    }

    protected function isOpenAi(?string $provider, ?string $model): bool
    {
        return $provider === 'openai'
            || ($provider === null && $model !== null && preg_match('/^(gpt-|o\d)/', $model));
    }
};
