<?php

namespace App\Support\Plant;

use App\Models\Asset;
use App\Models\LayoutItem;
use App\Models\Location;
use App\Models\PlantLayout;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modifiche alla pianta. Le posizioni si tengono sempre dentro il pavimento (con la
 * rotazione), arrotondate al passo della griglia; le dimensioni hanno limiti fissi.
 */
final class LayoutEditor
{
    /** Passo della griglia (metri) */
    public const STEP = 0.25;

    public const MIN_ITEM = 0.3;

    public const MAX_HEIGHT = 30.0;

    /**
     * @param  array{name: string, location_id: int|null, width: float|string, depth: float|string}  $data
     */
    public function saveLayout(?PlantLayout $layout, array $data): PlantLayout
    {
        $width = round((float) $data['width'], 2);
        $depth = round((float) $data['depth'], 2);
        foreach (['width' => $width, 'depth' => $depth] as $field => $value) {
            if ($value < PlantLayout::MIN_SIZE || $value > PlantLayout::MAX_SIZE) {
                throw ValidationException::withMessages([$field => __('plant.errors.size', ['min' => PlantLayout::MIN_SIZE, 'max' => PlantLayout::MAX_SIZE])]);
            }
        }

        return DB::transaction(function () use ($layout, $data, $width, $depth) {
            $creating = $layout === null;
            $layout ??= new PlantLayout;
            $layout->fill(['name' => trim($data['name']), 'location_id' => $data['location_id'], 'width' => $width, 'depth' => $depth]);
            if ($creating) {
                $layout->sort = (int) PlantLayout::query()->max('sort') + 1;
            }
            $changes = $creating ? [] : Audit::diff($layout);
            $layout->save();
            // Pavimento più piccolo: gli elementi rientrano dentro
            if (! $creating) {
                foreach ($layout->items()->get() as $item) {
                    $this->place($layout, $item, (float) $item->x, (float) $item->z);
                    $item->save();
                }
            }
            Audit::log($creating ? 'plant.layout_created' : 'plant.layout_updated', $layout, $layout->name, $changes);

            return $layout;
        });
    }

    public function deleteLayout(PlantLayout $layout): void
    {
        Audit::log('plant.layout_deleted', $layout, $layout->name);
        $layout->delete();
    }

    /** Mette una macchina nella pianta, in un posto libero se non si indica dove */
    public function addAsset(PlantLayout $layout, Asset $asset, ?float $x = null, ?float $z = null): LayoutItem
    {
        if (! $layout->placeableAssets()->whereKey($asset->id)->exists()) {
            throw ValidationException::withMessages(['asset' => __('plant.errors.not_placeable', ['code' => $asset->code])]);
        }
        $existing = LayoutItem::query()->with('layout')->where('asset_id', $asset->id)->first();
        if ($existing) {
            throw ValidationException::withMessages(['asset' => __('plant.errors.already_placed', ['code' => $asset->code, 'layout' => $existing->layout->name])]);
        }

        [$w, $d, $h] = Shapes::size($asset->category?->shape);
        $w = min($w, (float) $layout->width);
        $d = min($d, (float) $layout->depth);
        $item = new LayoutItem(['asset_id' => $asset->id, 'w' => $w, 'd' => $d, 'h' => $h, 'rotation' => 0]);
        [$x, $z] = $x !== null && $z !== null ? [$x, $z] : ($this->spotInZone($layout, $asset, $w, $d) ?? $this->freeSpot($layout, $w, $d));
        $this->place($layout, $item, $x, $z);
        $layout->items()->save($item);
        Audit::log('plant.item_added', $layout, $layout->name.' · '.$asset->code);

        return $item;
    }

    /** Zona del pavimento: una sotto-ubicazione (es. una linea) o un'etichetta libera */
    public function addZone(PlantLayout $layout, ?Location $location, ?string $label = null): LayoutItem
    {
        $label = $label !== null ? trim($label) : null;
        if ($location === null && ($label === null || $label === '')) {
            throw ValidationException::withMessages(['zone' => __('plant.errors.zone_name')]);
        }
        $w = round(min(12.0, max(2.0, (float) $layout->width * 0.4)), 2);
        $d = round(min(7.0, max(2.0, (float) $layout->depth * 0.3)), 2);
        $item = new LayoutItem(['location_id' => $location?->id, 'label' => $label ?: null, 'w' => $w, 'd' => $d, 'rotation' => 0]);
        [$x, $z] = $this->freeSpot($layout, $w, $d, zones: true);
        $this->place($layout, $item, $x, $z);
        $layout->items()->save($item);
        Audit::log('plant.item_added', $layout, $layout->name.' · '.$item->name());

        return $item;
    }

    public function move(LayoutItem $item, float $x, float $z): LayoutItem
    {
        $this->place($item->layout, $item, $x, $z);
        $item->save();

        return $item;
    }

    /**
     * @param  array{w?: float|string, d?: float|string, h?: float|string|null, rotation?: int|string, label?: string|null}  $data
     */
    public function update(LayoutItem $item, array $data): LayoutItem
    {
        $layout = $item->layout;
        foreach (['w' => (float) $layout->width, 'd' => (float) $layout->depth] as $field => $max) {
            if (array_key_exists($field, $data)) {
                $v = round((float) $data[$field], 2);
                if ($v < self::MIN_ITEM || $v > $max) {
                    throw ValidationException::withMessages([$field => __('plant.errors.item_size', ['min' => self::MIN_ITEM, 'max' => $max])]);
                }
                $item->setAttribute($field, $v);
            }
        }
        if (array_key_exists('h', $data) && ! $item->isZone()) {
            $h = $data['h'] === null || $data['h'] === '' ? null : round((float) $data['h'], 2);
            if ($h !== null && ($h < self::MIN_ITEM || $h > self::MAX_HEIGHT)) {
                throw ValidationException::withMessages(['h' => __('plant.errors.item_size', ['min' => self::MIN_ITEM, 'max' => self::MAX_HEIGHT])]);
            }
            $item->setAttribute('h', $h);
        }
        if (array_key_exists('rotation', $data)) {
            $item->rotation = (((int) $data['rotation']) % 360 + 360) % 360;
        }
        if (array_key_exists('label', $data) && $item->isZone()) {
            $label = trim((string) $data['label']);
            if ($label === '' && $item->location_id === null) {
                throw ValidationException::withMessages(['label' => __('plant.errors.zone_name')]);
            }
            $item->label = $label === '' ? null : mb_substr($label, 0, 80);
        }
        $this->place($layout, $item, (float) $item->x, (float) $item->z);
        $item->save();

        return $item;
    }

    public function remove(LayoutItem $item): void
    {
        Audit::log('plant.item_removed', $item->layout, $item->layout->name.' · '.$item->name());
        $item->delete();
    }

    /**
     * Mezze dimensioni dell'ingombro ruotato (rettangolo che contiene l'elemento).
     *
     * @return array{0: float, 1: float}
     */
    public static function halfExtents(float $w, float $d, int $rotation): array
    {
        $r = deg2rad($rotation);
        $c = abs(cos($r));
        $s = abs(sin($r));

        return [($w * $c + $d * $s) / 2, ($w * $s + $d * $c) / 2];
    }

    /** Posizione sulla griglia e dentro il pavimento */
    private function place(PlantLayout $layout, LayoutItem $item, float $x, float $z): void
    {
        [$hx, $hz] = self::halfExtents((float) $item->w, (float) $item->d, (int) $item->rotation);
        $W = (float) $layout->width;
        $D = (float) $layout->depth;
        $snap = fn (float $v) => round($v / self::STEP) * self::STEP;
        $clamp = fn (float $v, float $half, float $size) => $half * 2 >= $size ? $size / 2 : max($half, min($size - $half, $v));
        $item->setAttribute('x', round($clamp($snap($x), $hx, $W), 2));
        $item->setAttribute('z', round($clamp($snap($z), $hz, $D), 2));
    }

    /**
     * Se la pianta ha una zona per l'ubicazione della macchina (o per una che la contiene),
     * la macchina va dentro quella zona, se c'è posto.
     *
     * @return array{0: float, 1: float}|null
     */
    private function spotInZone(PlantLayout $layout, Asset $asset, float $w, float $d): ?array
    {
        $path = $asset->location?->path;
        if ($path === null) {
            return null;
        }
        $zones = $layout->items()->whereNotNull('location_id')->whereNull('asset_id')->with('location')->get()
            ->filter(fn (LayoutItem $z) => $z->location && str_starts_with($path, $z->location->path))
            // La zona più specifica (ubicazione più profonda) per prima
            ->sortByDesc(fn (LayoutItem $z) => strlen($z->location->path ?? ''));
        foreach ($zones as $zone) {
            [$hx, $hz] = self::halfExtents((float) $zone->w, (float) $zone->d, $zone->rotation);
            $spot = $this->freeSpot($layout, $w, $d, bounds: [(float) $zone->x - $hx, (float) $zone->z - $hz, (float) $zone->x + $hx, (float) $zone->z + $hz]);
            if ($spot !== null) {
                return $spot;
            }
        }

        return null;
    }

    /**
     * Primo posto libero (da in alto a sinistra) dove l'ingombro non tocca altre macchine
     * (o altre zone, per una zona). Se non c'è, il centro del pavimento; dentro un'area
     * ($bounds: x1, z1, x2, z2), null.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bounds
     * @return ($bounds is null ? array{0: float, 1: float} : array{0: float, 1: float}|null)
     */
    private function freeSpot(PlantLayout $layout, float $w, float $d, bool $zones = false, ?array $bounds = null): ?array
    {
        [$X0, $Z0, $W, $D] = $bounds ?? [0.0, 0.0, (float) $layout->width, (float) $layout->depth];
        $boxes = $layout->items()->get()
            ->filter(fn (LayoutItem $i) => $i->isZone() === $zones)
            ->map(function (LayoutItem $i) {
                [$hx, $hz] = self::halfExtents((float) $i->w, (float) $i->d, $i->rotation);

                return [(float) $i->x - $hx, (float) $i->z - $hz, (float) $i->x + $hx, (float) $i->z + $hz];
            })->all();
        $gap = 0.5;
        // Passo fine sui pavimenti piccoli, più largo su quelli grandi (al massimo ~200 prove per lato)
        $step = max(0.5, ceil(max($W - $X0, $D - $Z0) / 200 * 4) / 4);
        for ($z = $Z0 + $d / 2 + $gap; $z + $d / 2 <= $D; $z += $step) {
            for ($x = $X0 + $w / 2 + $gap; $x + $w / 2 <= $W; $x += $step) {
                $free = true;
                foreach ($boxes as [$x1, $z1, $x2, $z2]) {
                    if ($x - $w / 2 < $x2 + $gap && $x + $w / 2 > $x1 - $gap && $z - $d / 2 < $z2 + $gap && $z + $d / 2 > $z1 - $gap) {
                        $free = false;
                        break;
                    }
                }
                if ($free) {
                    return [$x, $z];
                }
            }
        }

        return $bounds === null ? [(float) $layout->width / 2, (float) $layout->depth / 2] : null;
    }
}
