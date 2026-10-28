<?php

namespace App\Livewire\Gdpr;

use App\Support\Audit\Audit;
use App\Support\Gdpr\Retention;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Impostazioni di conservazione dei dati, con l'anteprima di cosa farebbe stanotte la procedura.
 */
class RetentionSettings extends Component
{
    public string $anonymizeAfter = '';

    public string $auditMonths = '';

    public function mount(Retention $retention): void
    {
        $this->authorize('data.export');
        $this->anonymizeAfter = (string) ($retention->anonymizeAfterMonths() ?? '');
        $this->auditMonths = (string) ($retention->auditMonths() ?? '');
    }

    public function save(Retention $retention): void
    {
        $this->authorize('data.export');
        $this->validate([
            'anonymizeAfter' => ['nullable', 'integer', 'min:1', 'max:240'],
            'auditMonths' => ['nullable', 'integer', 'min:'.Retention::MIN_AUDIT_MONTHS, 'max:240'],
        ], attributes: ['anonymizeAfter' => __('gdpr.retention.anonymize'), 'auditMonths' => __('gdpr.retention.audit')]);
        $retention->save($this->anonymizeAfter !== '' ? (int) $this->anonymizeAfter : null, $this->auditMonths !== '' ? (int) $this->auditMonths : null);
        Audit::log('settings.retention', null, __('gdpr.retention.audit_line', ['a' => $this->anonymizeAfter ?: '—', 'r' => $this->auditMonths ?: '—']));
        $this->dispatch('toast', message: __('gdpr.retention.saved'));
    }

    public function render(Retention $retention): View
    {
        return view('livewire.gdpr.retention', [
            'users' => $retention->usersToAnonymize()->count(),
            'audit' => $retention->auditToDelete()->count(),
            'min' => Retention::MIN_AUDIT_MONTHS,
        ])->title(__('gdpr.retention.title'))->layoutData(['heading' => __('gdpr.title')]);
    }
}
