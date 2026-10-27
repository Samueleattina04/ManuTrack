<?php

use App\Actions\Maintenance\CreateWorkOrder;
use App\Models\Asset;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkRequest;
use Illuminate\Support\Str;

function makeRequest(User $author, array $attributes = []): WorkRequest
{
    $request = new WorkRequest(array_merge(['description' => 'Perdita di olio dal cilindro', 'priority' => 'media'], $attributes));
    $request->user_id = $author->id;
    $request->save();

    return $request;
}

/**
 * @param  list<int>  $assignees
 */
function makeOrder(User $creator, array $assignees = [], array $attributes = []): WorkOrder
{
    return app(CreateWorkOrder::class)->handle($creator, array_merge([
        'type' => 'correttivo',
        'title' => 'Sostituire la guarnizione',
        'priority' => 'media',
        'asset_id' => Asset::factory()->create()->id,
        'assignees' => $assignees,
    ], $attributes));
}

/** Corpo della richiesta di invio di una segnalazione dal telefono */
function reportPayload(array $overrides = []): array
{
    return array_merge([
        'description' => 'Rumore forte dal motore',
        'priority' => 'media',
        'machine_stopped' => '0',
        'client_uuid' => (string) Str::uuid(),
    ], $overrides);
}
