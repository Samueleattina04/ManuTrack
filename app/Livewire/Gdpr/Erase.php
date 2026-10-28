<?php

namespace App\Livewire\Gdpr;

use App\Models\DataExport;
use App\Support\Access\Access;
use App\Support\Gdpr\Eraser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Cancellazione totale a fine contratto: inventario, conferma forte (frase con il nome
 * dell'azienda, password, presa d'atto), poi il rapporto. Solo l'amministratore.
 */
class Erase extends Component
{
    public string $phrase = '';

    public string $password = '';

    public bool $understood = false;

    public function mount(): void
    {
        $this->authorize('data.export');
        abort_unless(auth()->user()->hasRole(Access::ADMIN), 403);
    }

    public function erase(Eraser $eraser): RedirectResponse|Redirector|null
    {
        abort_unless(auth()->user()->hasRole(Access::ADMIN), 403);
        $this->resetErrorBag();
        if (! $this->understood) {
            $this->addError('understood', __('gdpr.erase.understood_required'));

            return null;
        }
        try {
            $eraser->authorize(auth()->user(), $this->phrase, $this->password);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
            $this->password = '';

            return null;
        }
        $eraser->run(auth()->user(), 'pagina web');
        auth()->guard('web')->logout();

        return redirect('/');
    }

    public function render(Eraser $eraser): View
    {
        $inventory = $eraser->inventory();

        return view('livewire.gdpr.erase', [
            'rows' => array_sum($inventory['tables']),
            'tables' => count($inventory['tables']),
            'folders' => $inventory['folders'],
            'phraseText' => Eraser::phrase(),
            'recentExport' => DataExport::query()->where('status', 'pronto')->where('created_at', '>=', now()->subDays(7))->exists(),
        ])->title(__('gdpr.erase.title'))->layoutData(['heading' => __('gdpr.title')]);
    }
}
