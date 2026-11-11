<?php

namespace Platform\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * Kanal eines Berichts (Report). Ein Report hat N Kanaele — RSS-Pull, Email-Push,
 * PDF-Ablage, Slack-Webhook, etc. Der Kanal-Typ ist ein String, der ueber die
 * ChannelRendererRegistry auf einen konkreten Renderer aufgeloest wird.
 */
class VerbalizationChannel extends Model
{
    protected $table = 'verbalization_channels';

    protected $fillable = [
        'uuid',
        'verbalization_feed_id',
        'type',
        'config',
        'cadence',
        'is_active',
        'last_delivered_at',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'last_delivered_at' => 'datetime',
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
        return $this->belongsTo(VerbalizationFeed::class, 'verbalization_feed_id');
    }
}
