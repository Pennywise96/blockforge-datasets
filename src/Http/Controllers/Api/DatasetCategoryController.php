<?php

namespace Blockforge\Datasets\Http\Controllers\Api;

use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bf_dataset_categories', 'slug')->where(
                    fn ($query) => $query->where('type_id', $datasetType->id)
                ),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:bf_dataset_categories,id'],
        ]);

        $this->assertValidParentCategory(
            typeId: $datasetType->id,
            parentId: $validated['parent_id'] ?? null,
        );

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
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('bf_dataset_categories', 'slug')
                    ->where(fn ($query) => $query->where('type_id', $datasetCategory->type_id))
                    ->ignore($datasetCategory->id),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:bf_dataset_categories,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (array_key_exists('parent_id', $validated)) {
            $this->assertValidParentCategory(
                typeId: $datasetCategory->type_id,
                parentId: $validated['parent_id'],
                categoryId: $datasetCategory->id,
            );
        }

        $datasetCategory->update($validated);

        return response()->json($datasetCategory);
    }

    public function destroy(CmsDatasetCategory $datasetCategory): JsonResponse
    {
        $datasetCategory->delete();

        return response()->json(null, 204);
    }

    private function assertValidParentCategory(int $typeId, ?int $parentId, ?int $categoryId = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($categoryId !== null && $parentId === $categoryId) {
            throw ValidationException::withMessages([
                'parent_id' => ['A category cannot be its own parent.'],
            ]);
        }

        $parentCategory = CmsDatasetCategory::query()->find($parentId);

        if ($parentCategory === null || $parentCategory->type_id !== $typeId) {
            throw ValidationException::withMessages([
                'parent_id' => ['The selected parent category must belong to the same dataset type.'],
            ]);
        }

        if ($categoryId !== null && $this->isDescendantCategory($categoryId, $parentCategory)) {
            throw ValidationException::withMessages([
                'parent_id' => ['A category cannot be moved beneath one of its descendants.'],
            ]);
        }
    }

    private function isDescendantCategory(int $categoryId, CmsDatasetCategory $candidateParent): bool
    {
        $current = $candidateParent;

        while ($current->parent_id !== null) {
            if ($current->parent_id === $categoryId) {
                return true;
            }

            $current = CmsDatasetCategory::query()->find($current->parent_id);

            if ($current === null) {
                return false;
            }
        }

        return false;
    }
}
