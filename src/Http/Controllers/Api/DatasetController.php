<?php

namespace Blockforge\Datasets\Http\Controllers\Api;

use Blockforge\Cms\Models\CmsMediaItem;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetTranslation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DatasetController
{
    public function index(Request $request): JsonResponse
    {
        $query = CmsDataset::query()
            ->with(['type', 'translations', 'categories'])
            ->orderByDesc('date')
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->whereHas('type', fn ($q) => $q->where('slug', $request->string('type')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('category')) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $request->string('category')));
        }

        $datasets = $query->paginate($request->integer('per_page', 30))
            ->through(fn (CmsDataset $dataset) => $this->serializeDataset($dataset));

        return response()->json($datasets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type_id' => ['required', 'integer', 'exists:bf_dataset_types,id'],
            'slug' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:draft,published'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'config' => ['nullable', 'array'],
        ]);

        $dataset = CmsDataset::query()->create($validated);
        $dataset->load(['type', 'translations', 'categories']);

        return response()->json($this->serializeDataset($dataset), 201);
    }

    public function show(CmsDataset $dataset): JsonResponse
    {
        $dataset->load(['type', 'translations', 'categories']);

        return response()->json($this->serializeDataset($dataset));
    }

    public function update(Request $request, CmsDataset $dataset): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['sometimes', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:draft,published'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'config' => ['nullable', 'array'],
        ]);

        $dataset->update($validated);
        $dataset->load(['type', 'translations', 'categories']);

        return response()->json($this->serializeDataset($dataset));
    }

    public function destroy(CmsDataset $dataset): JsonResponse
    {
        $dataset->delete();

        return response()->json(null, 204);
    }

    public function updateTranslation(Request $request, CmsDataset $dataset, string $locale): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
        ]);

        $validated['data'] = $this->normalizeTranslationData($validated['data'] ?? null);

        $translation = CmsDatasetTranslation::query()->updateOrCreate(
            ['dataset_id' => $dataset->id, 'locale' => $locale],
            $validated,
        );

        return response()->json($this->serializeTranslation($translation->fresh()));
    }

    public function syncCategories(Request $request, CmsDataset $dataset): JsonResponse
    {
        $validated = $request->validate([
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'exists:bf_dataset_categories,id'],
        ]);

        $categoryIds = $validated['category_ids'];
        $matchingTypeCategoryCount = CmsDatasetCategory::query()
            ->whereIn('id', $categoryIds)
            ->where('type_id', $dataset->type_id)
            ->count();

        if ($matchingTypeCategoryCount !== count($categoryIds)) {
            throw ValidationException::withMessages([
                'category_ids' => ['All categories must belong to the same dataset type as the entry.'],
            ]);
        }

        $dataset->categories()->sync($categoryIds);
        $dataset->load('categories');

        return response()->json($dataset->categories);
    }

    /** @return array<string, mixed> */
    private function serializeDataset(CmsDataset $dataset): array
    {
        return [
            'id' => $dataset->id,
            'type_id' => $dataset->type_id,
            'type_slug' => $dataset->type?->slug,
            'slug' => $dataset->slug,
            'date' => $dataset->date?->toDateString(),
            'status' => $dataset->status,
            'sort_order' => $dataset->sort_order,
            'config' => $dataset->config,
            'translations' => $dataset->translations
                ->mapWithKeys(fn (CmsDatasetTranslation $translation) => [$translation->locale => $this->serializeTranslation($translation)])
                ->all(),
            'categories' => $dataset->categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ]),
            'created_at' => $dataset->created_at,
            'updated_at' => $dataset->updated_at,
        ];
    }

    /** @return array<string, mixed>|null */
    private function normalizeTranslationData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        if (array_key_exists('image', $data)) {
            $data['image'] = $this->normalizeMediaReference($data['image']);
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    private function normalizeMediaReference(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return null;
        }

        $mediaItemId = $value['media_item_id'] ?? $value['id'] ?? null;

        if (! is_numeric($mediaItemId)) {
            return null;
        }

        return [
            'media_item_id' => (int) $mediaItemId,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeTranslation(CmsDatasetTranslation $translation): array
    {
        return [
            'id' => $translation->id,
            'dataset_id' => $translation->dataset_id,
            'locale' => $translation->locale,
            'title' => $translation->title,
            'excerpt' => $translation->excerpt,
            'content' => $translation->content,
            'data' => $this->resolveTranslationData($translation->data ?? []),
            'created_at' => $translation->created_at,
            'updated_at' => $translation->updated_at,
        ];
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveTranslationData(array $data): array
    {
        if (array_key_exists('image', $data)) {
            $data['image'] = $this->resolveMediaReference($data['image']);
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    private function resolveMediaReference(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) && array_key_exists('path', $value)) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $mediaItemId = $value['media_item_id'] ?? $value['id'] ?? null;

        if (! is_numeric($mediaItemId)) {
            return null;
        }

        $mediaItem = CmsMediaItem::query()
            ->with('translations')
            ->find((int) $mediaItemId);

        if ($mediaItem === null) {
            return null;
        }

        return [
            'id' => $mediaItem->id,
            'category_id' => $mediaItem->category_id,
            'disk' => $mediaItem->disk,
            'path' => $mediaItem->path,
            'filename' => $mediaItem->filename,
            'mime_type' => $mediaItem->mime_type,
            'size' => $mediaItem->size,
            'width' => $mediaItem->width,
            'height' => $mediaItem->height,
            'focal_x' => $mediaItem->focal_x,
            'focal_y' => $mediaItem->focal_y,
            'translations' => $mediaItem->translations
                ->mapWithKeys(fn ($translation) => [$translation->locale => [
                    'alt' => $translation->alt,
                    'title' => $translation->title,
                ]])
                ->all(),
            'created_at' => $mediaItem->created_at,
        ];
    }
}
