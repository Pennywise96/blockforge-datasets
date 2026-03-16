<?php

namespace Blockforge\Datasets\Http\Controllers\Api;

use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetCategoryController
{
    public function index(CmsDatasetType $datasetType): JsonResponse
    {
        $categories = CmsDatasetCategory::query()
            ->where('type_id', $datasetType->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request, CmsDatasetType $datasetType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:bf_dataset_categories,id'],
        ]);

        $category = CmsDatasetCategory::query()->create([
            ...$validated,
            'type_id' => $datasetType->id,
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, CmsDatasetCategory $datasetCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:bf_dataset_categories,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $datasetCategory->update($validated);

        return response()->json($datasetCategory);
    }

    public function destroy(CmsDatasetCategory $datasetCategory): JsonResponse
    {
        $datasetCategory->delete();

        return response()->json(null, 204);
    }
}
