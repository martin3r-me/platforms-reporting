<?php

namespace Platform\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * Eine Sammel- und Verbalisierungs-Rezeptur.
 *
 * Atomar: bedient EINEN subject_type, EIN Sammler kennt seine eigenen
 * source-Keys und interpretiert sie. Unbekannte Keys werden ignoriert.
 *
 * Scoping: key+team_id eindeutig. team_id null = globaler Default.
 */
class VerbalizationRecipe extends Model
{
    protected $table = 'verbalization_recipes';

    protected $fillable = [
        'uuid',
        'key',
        'name',
        'description',
        'subject_type',
        'sources',
        'style',
        'guards',
        'llm',
        'include_natures',
        'since_window',
        'freshness_requirement',
        'team_id',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'sources' => 'array',
        'style' => 'array',
        'guards' => 'array',
        'llm' => 'array',
        'include_natures' => 'array',
        'is_active' => 'boolean',
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
