<?php

namespace App\Livewire\Gdpr;

use App\Models\User;
use App\Support\Access\Access;
use App\Support\Gdpr\PersonAnonymizer;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Anonimizzazione di una persona (con o senza account) con anteprima di dove compare
 * e conferma scritta. Solo l'amministratore.
 */
class Anonymize extends Component
{
    #[Url(as: 'persona')]
    public string $userId = '';

    /** Varianti del nome da cercare, una per riga (es. "M. Rossi", un soprannome) */
    public string $extra = '';

    public string $confirm = '';

    /** @var array{pseudonym: string, replaced: array<string, int>}|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->authorize('data.export');
        abort_unless(auth()->user()->hasRole(Access::ADMIN), 403);
    }

    public function anonymize(PersonAnonymizer $anonymizer): void
    {
        abort_unless(auth()->user()->hasRole(Access::ADMIN), 403);
        $this->resetErrorBag();
        if (trim($this->confirm) !== 'ANONIMIZZA') {
            $this->addError('confirm', __('gdpr.anonymize.wrong_confirm'));

            return;
        }
        try {
            $this->result = $anonymizer->anonymize($this->user(), $this->extraTerms(), auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }

            return;
        }
        $this->reset('userId', 'extra', 'confirm');
    }

    private function user(): ?User
    {
        return ctype_digit($this->userId) ? User::query()->find((int) $this->userId) : null;
    }

    /** @return list<string> */
    private function extraTerms(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $this->extra) ?: [])));
    }

    public function render(PersonAnonymizer $anonymizer): View
    {
        $user = $this->user();
        $terms = PersonAnonymizer::terms($user, $this->extraTerms());

        return view('livewire.gdpr.anonymize', [
            'people' => User::query()->whereNull('anonymized_at')->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'email', 'is_active', 'deactivated_at']),
            'user' => $user,
            'terms' => $terms,
            'preview' => $terms === [] ? [] : $anonymizer->preview($terms),
            'anonymized' => User::query()->whereNotNull('anonymized_at')->latest('anonymized_at')->limit(20)->get(['id', 'name', 'anonymized_at']),
        ])->title(__('gdpr.anonymize.title'))->layoutData(['heading' => __('gdpr.title')]);
    }
}
