<?php

/**
 * Showcase Livewire Component — lebendes nx-Komponenten-Verzeichnis
 *
 * Rendert JEDE x-nx-* Komponente einmal live, mit kurzer „wann nutzen"-Info.
 * Dient als visuelle Referenz + Copy-Paste-Quelle + Frühwarnung (bricht eine
 * Komponente oder fehlt eine Tailwind-Klasse, sieht man es hier sofort).
 *
 * WICHTIG: Beim Hinzufügen einer neuen nx-Komponente hier einen Block ergänzen.
 * Voll DB-frei — alle Beispieldaten liegen inline in der View.
 */

namespace Platform\Reporting\Livewire;

use Livewire\Component;

class Showcase extends Component
{
    /** Aktiver Tab im Tabs-Beispiel */
    public string $tab = 'eins';

    /** Modal-Sichtbarkeit (je Größe eins) */
    public bool $showModalSm = false;
    public bool $showModalMd = false;
    public bool $showModalLg = false;

    /** Formularfeld-Bindings (nur fürs Live-Beispiel) */
    public string $demoText = '';
    public ?int $demoNumber = null;
    public string $demoDate = '';
    public ?string $demoDatetime = null;
    public ?string $demoSelect = null;
    public bool $demoCheckbox = true;
    public string $demoTextarea = '';

    public function render()
    {
        return view('reporting::livewire.showcase')
            ->layout('platform::layouts.app');
    }
}
