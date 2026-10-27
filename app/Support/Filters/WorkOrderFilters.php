<?php

namespace App\Support\Filters;

use App\Enums\Priority;
use App\Enums\WorkOrderType;
use App\Livewire\WorkOrders\Index;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtri dell'elenco degli ordini, gli stessi per la pagina e per l'esportazione.
 * I nomi sono quelli dell'indirizzo della pagina (vista, miei, q, priorita, tipo, macchina).
 */
final class WorkOrderFilters
{
    public function __construct(
        public string $tab = 'da_fare',
        public bool $mine = false,
        public string $search = '',
        public string $priority = '',
        public string $type = '',
        public string $asset = '',
    ) {}

    /**
     * Dai parametri dell'indirizzo. Senza "miei", chi non assegna gli ordini parte dai propri (come la pagina).
     *
     * @param  array<string, mixed>  $q
     */
    public static function fromQuery(array $q, User $user): self
    {
        $tab = (string) ($q['vista'] ?? 'da_fare');

        return new self(
            tab: array_key_exists($tab, Index::TABS) ? $tab : 'da_fare',
            mine: array_key_exists('miei', $q) ? filter_var($q['miei'], FILTER_VALIDATE_BOOLEAN) : ! $user->can('work_orders.assign'),
            search: (string) ($q['q'] ?? ''),
            priority: (string) ($q['priorita'] ?? ''),
            type: (string) ($q['tipo'] ?? ''),
            asset: (string) ($q['macchina'] ?? ''),
        );
    }

    /**
     * Filtri senza la scheda di stato (per i conteggi delle schede).
     *
     * @return Builder<WorkOrder>
     */
    public function base(User $user): Builder
    {
        return WorkOrder::query()
            ->when($this->mine, fn (Builder $q) => $q->assignedTo($user))
            ->search($this->search)
            ->when(Priority::tryFrom($this->priority), fn (Builder $q) => $q->where('priority', $this->priority))
            ->when(WorkOrderType::tryFrom($this->type), fn (Builder $q) => $q->where('type', $this->type))
            ->when(ctype_digit($this->asset), fn (Builder $q) => $q->where('asset_id', (int) $this->asset));
    }

    /**
     * Tutti i filtri, scheda compresa, con l'ordinamento della pagina.
     *
     * @return Builder<WorkOrder>
     */
    public function query(User $user): Builder
    {
        $statuses = Index::TABS[$this->tab] ?? [];

        return $this->base($user)
            ->when($statuses !== [], fn (Builder $q) => $q->whereIn('status', $statuses))
            ->when(in_array($this->tab, ['chiusi', 'da_chiudere'], true), fn (Builder $q) => $q->latest('updated_at'), fn (Builder $q) => $q->byUrgency());
    }
}
