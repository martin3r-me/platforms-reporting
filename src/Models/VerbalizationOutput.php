<?php

namespace Platform\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * Eine einzelne Verbalisierung — kann feed-getrieben oder ad-hoc sein.
 *
 * Persistierter Verlauf — auch ohne Feed sinnvoll, denn ein RSS-Feed im
 * "history"-Strategy-Modus zieht aus exakt dieser Tabelle.
 */
class VerbalizationOutput extends Model
{
    protected $table = 'verbalization_outputs';

    protected $fillable = [
        'uuid',
        'feed_id',
        'recipe_key',
        'subject_type',
        'subject_id',
        'subject_label',
        'prose',
        'llm_provider',
        'llm_model',
        'input_tokens',
        'output_tokens',
        'state_hash',
        'team_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(VerbalizationFeed::class, 'feed_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
