<?php

namespace Blockforge\Datasets\Http\Controllers\Api;

use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetTypeController
{
    public function index(): JsonResponse
    {
        $types = CmsDatasetType::query()->orderBy('sort_order')->get();

        return response()->json($types);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:cms_dataset_types,slug'],
            'description' => ['nullable', 'string'],
        ]);

        $type = CmsDatasetType::query()->create($validated);

        return response()->json($type, 201);
    }

    public function update(Request $request, CmsDatasetType $datasetType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:cms_dataset_types,slug,'.$datasetType->id],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $datasetType->update($validated);

        return response()->json($datasetType);
    }

    public function destroy(CmsDatasetType $datasetType): JsonResponse
    {
        $datasetType->delete();

        return response()->json(null, 204);
    }
}
