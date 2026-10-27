<?php

namespace App\Actions\Assets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessage;
use App\Support\Push\PushUrgency;
use Illuminate\Support\Collection;

/**
 * Cambia lo stato di una macchina e avvisa i responsabili.
 *
 * - Guasto o fermo di una macchina di criticità A (ferma la linea): notifica
 *   push urgente, inviata subito dopo la risposta senza aspettare il cron.
 * - Guasto o fermo delle altre macchine, e ritorno in funzione: notifica normale, in coda.
 */
class ChangeAssetStatus
{
    public function __construct(private PushDispatcher $push) {}

    public function handle(Asset $asset, AssetStatus $status, User $actor, ?string $note = null): void
    {
        $previous = $asset->status;
        if ($previous === $status) {
            return;
        }

        $asset->forceFill(['status' => $status, 'status_changed_at' => now()])->save();
        Audit::log('assets.status_changed', $asset, trim($asset->code.' · '.$status->label().($note ? ' · '.$note : '')), ['status' => [$previous->value, $status->value]], $actor);

        $down = in_array($status, [AssetStatus::Fault, AssetStatus::Stopped], true);
        $backUp = $status === AssetStatus::Operational && in_array($previous, [AssetStatus::Fault, AssetStatus::Stopped], true);
        if (! $down && ! $backUp) {
            return;
        }

        $urgent = $down && $asset->stopsLine();
        $where = $asset->location?->fullName();

        $this->push->send(self::recipients($actor), new PushMessage(
            title: __($down ? ($urgent ? 'assets.push.down_urgent_title' : 'assets.push.down_title') : 'assets.push.up_title', [
                'status' => $status->label(),
                'asset' => $asset->code.' '.$asset->name,
            ]),
            body: collect([
                $where,
                __('assets.push.by', ['name' => $actor->name]),
                $urgent ? __('assets.push.line_stopped') : null,
                $note,
            ])->filter()->implode(' · '),
            url: route('assets.show', $asset, absolute: false),
            tag: 'asset-'.$asset->id,
        ), $urgent ? PushUrgency::Immediate : PushUrgency::Normal);
    }

    /**
     * Chi gestisce le segnalazioni (responsabili e amministratori), escluso chi ha fatto l'azione.
     *
     * @return Collection<int, User>
     */
    public static function recipients(User $actor): Collection
    {
        return User::permission('requests.triage')
            ->where('is_active', true)
            ->whereKeyNot($actor->id)
            ->get();
    }
}
