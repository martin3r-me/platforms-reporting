<?php

/*
|--------------------------------------------------------------------------
| Verbalization-Config (aus Core übernommen)
|--------------------------------------------------------------------------
| Bewusst derselbe Config-Key ('verbalization') wie im Core. mergeConfigFrom
| überschreibt bestehende Keys NICHT — solange Core parallel läuft, gewinnt
| dessen Config; fehlt Core, füllt dieses Modul den Fallback. Beim Contract-
| Schritt wandert die Config-Hoheit vollständig hierher.
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Default LLM provider for the Verbalizer
    |--------------------------------------------------------------------------
    | Key matches what providers report via getName(). Falls back to the first
    | available provider when null.
    */
    'default_provider' => env('VERBALIZATION_PROVIDER', 'anthropic'),
];
