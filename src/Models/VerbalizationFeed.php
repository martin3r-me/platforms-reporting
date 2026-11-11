<?php

namespace Platform\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

/**
 * Verbalization-Feed — abonnierbare URL fuer eine oder mehrere Verbalisierungen.
 *
 * Drei Verhaltensmodi via subject_selector.mode:
 *   single — eine konkrete Entity (Variante A: Verlaufsticker)
 *   list   — Liste von IDs (Variante B/C explizit)
 *   entity — alle Subjects an einer Organization-Entity (Variante B/C dynamisch)
 *
 * Mixed-Type: recipes-Map definiert pro subject_type die Recipe.
 */
class VerbalizationFeed extends Model
{
    protected $table = 'verbalization_feeds';

    protected $fillable = [
        'uuid',
        'slug',
        'title',
        'description',
        'subject_type',
        'subject_selector',
        'recipes',
        'item_strategy',
        'llm_provider',
        'llm_model',
        'access',
        'refresh_strategy',
        'retention_items',
        'is_active',
        'last_refreshed_at',
        'team_id',
        'owner_user_id',
    ];

    protected $casts = [
        'subject_selector' => 'array',
        'recipes' => 'array',
        'is_active' => 'boolean',
        'last_refreshed_at' => 'datetime',
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

    public function outputs(): HasMany
    {
        return $this->hasMany(VerbalizationOutput::class, 'feed_id')->orderByDesc('created_at');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function rotateToken(): void
    {
        do {
            $uuid = UuidV7::generate();
        } while (self::where('uuid', $uuid)->exists());
        $this->uuid = $uuid;
    }
}
