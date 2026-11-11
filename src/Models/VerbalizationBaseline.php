<?php

namespace Platform\Reporting\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Zeitreihen-Snapshot fuer Verbalization-Metriken. Ermoeglicht ad-hoc
 * Delta-Rechnung ueber beliebige Fenster (7d/30d/...), ohne dass die
 * Recipe / der Feed die Fenster im Voraus deklarieren muss.
 *
 * Baseline ist Subject-lokal (subject_type + subject_id), NICHT Feed-lokal.
 * Damit teilen sich mehrere Feeds mit unterschiedlichen Fenstern die
 * Zeitreihe. Retention geschieht ueber periodisches Aufraeumen alter Rows.
 */
class VerbalizationBaseline extends Model
{
    protected $table = 'verbalization_baselines';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'metrics',
        'captured_at',
    ];

    protected $casts = [
        'metrics' => 'array',
        'captured_at' => 'datetime',
    ];
}
