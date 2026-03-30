<?php

namespace Blockforge\Datasets\Http\Controllers\Api;

use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetTranslation;
use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\Schemas\DatasetSchema;
use Blockforge\Datasets\Schemas\DatasetSchemaMediaNormalizer;
use Blockforge\Datasets\Schemas\DatasetSchemaRegistry;
use Blockforge\Datasets\Schemas\DatasetSchemaValidator;
use Blockforge\Datasets\Support\DatasetVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DatasetController
{
    public function __construct(
        private readonly DatasetSchemaRegistry $schemaRegistry,
        private readonly DatasetSchemaValidator $schemaValidator,
        private readonly DatasetSchemaMediaNormalizer $mediaNormalizer,
        private readonly DatasetVisibilityService $visibilityService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = CmsDataset::query()
            ->with(['type', 'translations', 'categories', 'visibilityRanges'])
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->whereHas('type', fn ($q) => $q->where('code', $request->string('type')));
        }

        $visibilityFilter = $request->string('visibility')->toString();

        if ($visibilityFilter === '' && $request->filled('status')) {
            $visibilityFilter = $request->string('status')->toString() === 'published' ? 'visible' : 'disabled';
        }

        $this->applyEditorVisibilityFilter($query, $visibilityFilter === '' ? 'all' : $visibilityFilter);

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
            'visibility_mode' => ['sometimes', 'in:disabled,always,scheduled'],
            'visibility_ranges' => ['nullable', 'array'],
            'visibility_ranges.*.starts_at' => ['nullable', 'date'],
            'visibility_ranges.*.ends_at' => ['nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'config' => ['nullable', 'array'],
        ]);

        $datasetType = CmsDatasetType::query()->findOrFail($validated['type_id']);
        $schema = $this->resolveSchemaForType($datasetType);
        $normalizedCustomData = $this->schemaValidator->validate(
            $schema,
            $validated['config'] ?? [],
            [],
        );

        $dataset = CmsDataset::query()->create([
            'type_id' => $datasetType->id,
            'slug' => $validated['slug'],
            'visibility_mode' => $this->resolveVisibilityMode($validated),
            'sort_order' => $validated['sort_order'] ?? 0,
            'config' => $this->mediaNormalizer->normalizeConfig($schema, $normalizedCustomData['config']),
        ]);

        $this->syncVisibilityRanges(
            $dataset,
            $dataset->visibility_mode === 'scheduled'
                ? ($validated['visibility_ranges'] ?? [])
                : [],
        );

        $dataset->load(['type', 'translations', 'categories', 'visibilityRanges']);

        return response()->json($this->serializeDataset($dataset), 201);
    }

    public function show(CmsDataset $dataset): JsonResponse
    {
        $dataset->load(['type', 'translations', 'categories', 'visibilityRanges']);

        return response()->json($this->serializeDataset($dataset));
    }

    public function update(Request $request, CmsDataset $dataset): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['sometimes', 'string', 'max:255'],
            'visibility_mode' => ['sometimes', 'in:disabled,always,scheduled'],
            'visibility_ranges' => ['nullable', 'array'],
            'visibility_ranges.*.starts_at' => ['nullable', 'date'],
            'visibility_ranges.*.ends_at' => ['nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'config' => ['nullable', 'array'],
        ]);

        $dataset->loadMissing('type', 'translations');
        $schema = $this->resolveSchemaForType($dataset->type);
        $activeLocale = app()->bound('cms.locale') ? app('cms.locale') : app()->getLocale();
        $existingTranslationData = $this->existingTranslationData($dataset, $activeLocale);
        $nextConfig = array_key_exists('config', $validated)
            ? array_replace_recursive($dataset->config ?? [], $validated['config'] ?? [])
            : ($dataset->config ?? []);

        $normalizedCustomData = $this->schemaValidator->validate(
            $schema,
            $nextConfig,
            $existingTranslationData,
        );

        $dataset->update([
            'slug' => $validated['slug'] ?? $dataset->slug,
            'visibility_mode' => $this->resolveVisibilityMode($validated, $dataset->visibility_mode),
            'sort_order' => $validated['sort_order'] ?? $dataset->sort_order,
            'config' => $this->mediaNormalizer->normalizeConfig($schema, $normalizedCustomData['config']),
        ]);

        if (array_key_exists('visibility_ranges', $validated) || $dataset->visibility_mode !== 'scheduled') {
            $this->syncVisibilityRanges(
                $dataset,
                $dataset->visibility_mode === 'scheduled'
                    ? ($validated['visibility_ranges'] ?? [])
                    : [],
            );
        }

        $dataset->load(['type', 'translations', 'categories', 'visibilityRanges']);

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
            'data' => ['nullable', 'array'],
        ]);

        $dataset->loadMissing('type');

        $existingTranslation = CmsDatasetTranslation::query()
            ->where('dataset_id', $dataset->id)
            ->where('locale', $locale)
            ->first();

        $existingTranslationData = $this->translationPayloadWithLegacyFields($existingTranslation);
        $mergedTranslationData = array_replace_recursive(
            $existingTranslationData,
            $validated['data'] ?? [],
        );

        $schema = $this->resolveSchemaForType($dataset->type);
        $normalizedCustomData = $this->schemaValidator->validate(
            $schema,
            $dataset->config ?? [],
            $mergedTranslationData,
        );

        $translation = CmsDatasetTranslation::query()->updateOrCreate(
            ['dataset_id' => $dataset->id, 'locale' => $locale],
            [
                'title' => $validated['title'],
                'data' => $this->mediaNormalizer->normalizeTranslationData($schema, $normalizedCustomData['data']),
            ],
        );

        return response()->json($this->serializeTranslation($translation->fresh(), $schema));
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

    /**
     * @return array<string, mixed>
     */
    private function serializeDataset(CmsDataset $dataset): array
    {
        $schema = $dataset->type instanceof CmsDatasetType
            ? $this->resolveSchemaForType($dataset->type)
            : null;

        return [
            'id' => $dataset->id,
            'type_id' => $dataset->type_id,
            'type_code' => $dataset->type?->code,
            'slug' => $dataset->slug,
            'visibility_mode' => $dataset->visibility_mode,
            'visibility_ranges' => $dataset->visibilityRanges
                ->map(fn ($range) => [
                    'id' => $range->id,
                    'starts_at' => $range->starts_at?->toISOString(),
                    'ends_at' => $range->ends_at?->toISOString(),
                ])
                ->all(),
            'is_visible_now' => $this->visibilityService->isVisibleNow($dataset),
            'visibility_label' => $this->visibilityService->labelFor($dataset),
            'sort_order' => $dataset->sort_order,
            'config' => $this->mediaNormalizer->resolveConfig($schema, $schema?->extractNonTranslatableData($dataset->config ?? []) ?? ($dataset->config ?? [])),
            'translations' => $dataset->translations
                ->mapWithKeys(fn (CmsDatasetTranslation $translation) => [$translation->locale => $this->serializeTranslation($translation, $schema)])
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

    /**
     * @return array<string, mixed>
     */
    private function serializeTranslation(CmsDatasetTranslation $translation, ?DatasetSchema $schema = null): array
    {
        $translationData = $this->translationPayloadWithLegacyFields($translation);
        $resolvedData = $this->mediaNormalizer->resolveTranslationData(
            $schema,
            $schema?->extractTranslatableData($translationData) ?? $translationData,
        );

        return [
            'id' => $translation->id,
            'dataset_id' => $translation->dataset_id,
            'locale' => $translation->locale,
            'title' => $translation->title,
            'data' => $resolvedData,
            'created_at' => $translation->created_at,
            'updated_at' => $translation->updated_at,
        ];
    }

    private function resolveSchemaForType(?CmsDatasetType $type): ?DatasetSchema
    {
        if (! $type instanceof CmsDatasetType) {
            return null;
        }

        return $this->schemaRegistry->find($type->code);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveVisibilityMode(array $validated, ?string $fallback = null): string
    {
        $mode = $validated['visibility_mode'] ?? null;

        if (is_string($mode) && in_array($mode, ['disabled', 'always', 'scheduled'], true)) {
            return $mode;
        }

        if (array_key_exists('status', $validated)) {
            return $validated['status'] === 'published' ? 'always' : 'disabled';
        }

        return $fallback ?? 'disabled';
    }

    /**
     * @param  array<int, array<string, mixed>>  $ranges
     */
    private function syncVisibilityRanges(CmsDataset $dataset, array $ranges): void
    {
        $normalizedRanges = $this->visibilityService->normalizeRanges($ranges);

        $dataset->visibilityRanges()->delete();

        foreach ($normalizedRanges as $index => $range) {
            $dataset->visibilityRanges()->create([
                'sort_order' => $index,
                'starts_at' => $range['starts_at'],
                'ends_at' => $range['ends_at'],
            ]);
        }
    }

    /**
     * @param  Builder<CmsDataset>  $query
     */
    private function applyEditorVisibilityFilter($query, string $visibility): void
    {
        match ($visibility) {
            'visible' => $this->visibilityService->applyVisibleNow($query),
            'disabled' => $query->where('visibility_mode', 'disabled'),
            'always' => $query->where('visibility_mode', 'always'),
            'scheduled' => $query->where('visibility_mode', 'scheduled'),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function existingTranslationData(CmsDataset $dataset, string $locale): array
    {
        $translation = $dataset->translations->firstWhere('locale', $locale);

        return $this->translationPayloadWithLegacyFields($translation);
    }

    /**
     * @return array<string, mixed>
     */
    private function translationPayloadWithLegacyFields(?CmsDatasetTranslation $translation): array
    {
        if (! $translation instanceof CmsDatasetTranslation) {
            return [];
        }

        $data = is_array($translation->data) ? $translation->data : [];

        if ($translation->excerpt !== null && ! array_key_exists('excerpt', $data)) {
            $data['excerpt'] = $translation->excerpt;
        }

        if ($translation->content !== null && ! array_key_exists('content', $data)) {
            $data['content'] = $translation->content;
        }

        return $data;
    }
}
