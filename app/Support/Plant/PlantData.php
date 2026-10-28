<?php

namespace App\Support\Plant;

use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\LayoutItem;
use App\Models\PlantLayout;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * Dati della pianta per la vista 3D e per quella 2D (stessi dati, stesse coordinate).
 * Ogni macchina porta lo stato attuale, gli ordini aperti e i collegamenti che la persona può usare.
 */
final class PlantData
{
    /**
     * @return array{layout: array<string, mixed>, zones: list<array<string, mixed>>, items: list<array<string, mixed>>, counts: array<string, int>}
     */
    public static function for(PlantLayout $layout, User $user): array
    {
        $items = $layout->items()->with(['asset.category', 'location'])->get();
        $assets = $items->whereNotNull('asset_id');
        $statuses = self::statuses($assets->pluck('asset_id')->all(), $user);

        return [
            'layout' => ['id' => $layout->id, 'name' => $layout->name, 'width' => (float) $layout->width, 'depth' => (float) $layout->depth],
            'zones' => $items->filter(fn (LayoutItem $i) => $i->isZone())->map(fn (LayoutItem $i) => [
                'id' => $i->id,
                'label' => $i->name(),
                ...self::geometry($i),
            ])->values()->all(),
            'items' => $assets->map(function (LayoutItem $i) use ($statuses, $user) {
                /** @var Asset $a */
                $a = $i->asset;

                return [
                    'id' => $i->id,
                    'asset_id' => $a->id,
                    'code' => $a->code,
                    'name' => $a->name,
                    'shape' => Shapes::normalize($a->category?->shape),
                    'category' => $a->category?->name,
                    'criticality' => $a->criticality->value,
                    'url' => route('assets.show', $a),
                    'orders_url' => $user->can('work_orders.view') ? route('work-orders.index', ['macchina' => $a->id, 'vista' => 'tutti', 'miei' => 0]) : null,
                    'report_url' => $user->can('requests.create') ? route('requests.create', ['id' => $a->id]) : null,
                    ...self::geometry($i),
                    'h' => $i->h !== null ? (float) $i->h : Shapes::size($a->category?->shape)[2],
                    ...$statuses[$a->id],
                ];
            })->values()->all(),
            'counts' => collect($statuses)->countBy('status')->all(),
        ];
    }

    /**
     * Stato attuale delle macchine (per l'aggiornamento periodico della vista).
     *
     * @param  list<int>  $assetIds
     * @return array<int, array{status: string, tone: string, status_label: string, open_orders: int|null}>
     */
    public static function statuses(array $assetIds, User $user): array
    {
        if ($assetIds === []) {
            return [];
        }
        $open = $user->can('work_orders.view')
            ? WorkOrder::query()->whereIn('asset_id', $assetIds)->whereIn('status', array_map(fn ($s) => $s->value, WorkOrderStatus::active()))
                ->toBase()->selectRaw('asset_id, count(*) as n')->groupBy('asset_id')->pluck('n', 'asset_id')
            : collect();

        /** @var Collection<int, Asset> $assets */
        $assets = Asset::query()->whereIn('id', $assetIds)->get(['id', 'status']);

        return $assets->mapWithKeys(fn (Asset $a) => [$a->id => [
            'status' => $a->status->value,
            'tone' => $a->status->tone(),
            'status_label' => $a->status->label(),
            'open_orders' => $user->can('work_orders.view') ? (int) ($open[$a->id] ?? 0) : null,
        ]])->all();
    }

    /** @return array{x: float, z: float, w: float, d: float, rotation: int} */
    private static function geometry(LayoutItem $i): array
    {
        return ['x' => (float) $i->x, 'z' => (float) $i->z, 'w' => (float) $i->w, 'd' => (float) $i->d, 'rotation' => $i->rotation];
    }
}
