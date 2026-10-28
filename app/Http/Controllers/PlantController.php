<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\PlantLayout;
use App\Support\Plant\AssetModel;
use App\Support\Plant\PlantData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stabilimento (vista 3D/2D della pianta) e modelli 3D delle macchine.
 */
class PlantController extends Controller
{
    public function index(Request $request): View
    {
        $layouts = PlantLayout::query()->orderBy('sort')->orderBy('name')->get();
        $current = $layouts->firstWhere('id', $request->integer('pianta')) ?? $layouts->first();
        $user = $request->user();

        return view('plant.index', [
            'layouts' => $layouts,
            'current' => $current,
            'data' => $current ? PlantData::for($current, $user) : null,
            'unplaced' => $current && $user->can('assets.manage') ? $current->placeableAssets()->whereDoesntHave('plantItem')->count() : 0,
            'canManage' => $user->can('assets.manage'),
        ]);
    }

    /** Stato attuale delle macchine della pianta (aggiornamento ogni 30 secondi) */
    public function status(Request $request, PlantLayout $plant): JsonResponse
    {
        $ids = $plant->items()->whereNotNull('asset_id')->pluck('asset_id')->map(fn ($id) => (int) $id)->all();

        return response()->json(PlantData::statuses($ids, $request->user()))->header('Cache-Control', 'no-store, private');
    }

    public function model(Asset $asset): Response
    {
        abort_unless($asset->model_path && Storage::disk(AssetModel::disk())->exists($asset->model_path), 404);

        return Storage::disk(AssetModel::disk())->response($asset->model_path, $asset->code.'.glb', [
            'Content-Type' => 'model/gltf-binary',
            'X-Content-Type-Options' => 'nosniff',
            // Il nome del file cambia a ogni caricamento: il browser può tenerlo a lungo
            'Cache-Control' => 'private, max-age=604800',
        ], 'inline');
    }

    public function uploadModel(Request $request, Asset $asset, AssetModel $models): RedirectResponse
    {
        $request->validate(['model' => ['required', 'file']], ['model.required' => __('plant.model.invalid')]);
        $models->store($asset, $request->file('model'));

        return redirect()->route('assets.show', $asset)->with('status', __('plant.model.uploaded'));
    }

    public function deleteModel(Asset $asset, AssetModel $models): RedirectResponse
    {
        $models->delete($asset);

        return redirect()->route('assets.show', $asset)->with('status', __('plant.model.deleted'));
    }
}
