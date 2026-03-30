<?php

namespace Blockforge\Datasets\ViewHelpers;

use Blockforge\Cms\ViewHelpers\ViewHelper;
use Blockforge\Datasets\Elements\DatasetObject;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Support\DatasetArchiveQueryFactory;
use Blockforge\Datasets\Support\DatasetDetailPageService;
use Blockforge\Datasets\Support\DatasetTypeResolver;
use Blockforge\Datasets\Support\DatasetVisibilityService;

class DatasetItemsViewHelper extends ViewHelper
{
    public function render(
        ?string $type = null,
        string $as = 'item',
        string $iteration = 'iteration',
        ?int $limit = null,
        ?string $category = null,
        ?string $search = null,
        ?int $pageNumber = null,
        string $orderBy = 'created_at',
        string $direction = 'desc',
        string $visibility = 'visible',
        ?string $detailBase = null,
    ): string {
        $locale = app()->bound('cms.locale') ? app('cms.locale') : app()->getLocale();
        $items = $this->fetchItems($locale, $detailBase, $type, $limit, $category, $search, $pageNumber, $orderBy, $direction, $visibility);

        $output = '';
        $total = count($items);

        foreach ($items as $i => $item) {
            $loop = (object) [
                'index' => $i,
                'cycle' => $i + 1,
                'total' => $total,
                'isFirst' => $i === 0,
                'isLast' => $i === $total - 1,
                'isEven' => $i % 2 === 0,
                'isOdd' => $i % 2 !== 0,
            ];

            $output .= $this->renderChildren([
                'item' => $item,
                'iteration' => $loop,
                $as => $item,
                $iteration => $loop,
            ]);
        }

        return $output;
    }

    /** @return array{category:?string,page:?int,search:?string} */
    private function resolveContextFilters(): array
    {
        $filters = app()->bound('cms.dataset_filters') && is_array(app('cms.dataset_filters'))
            ? app('cms.dataset_filters')
            : [];

        $search = request()->query('q');

        return [
            'category' => is_string($filters['category'] ?? null) && $filters['category'] !== ''
                ? $filters['category']
                : null,
            'page' => is_numeric($filters['page'] ?? null) && (int) $filters['page'] > 0
                ? (int) $filters['page']
                : null,
            'search' => is_string($search) && trim($search) !== ''
                ? trim($search)
                : null,
        ];
    }

    /** @return DatasetObject[] */
    private function fetchItems(
        string $locale,
        ?string $detailBase,
        ?string $type,
        ?int $limit,
        ?string $category,
        ?string $search,
        ?int $pageNumber,
        string $orderBy,
        string $direction,
        string $visibility,
    ): array {
        $typeModel = app(DatasetTypeResolver::class)->resolve($type);

        if (! $typeModel) {
            return [];
        }

        $filters = $this->resolveContextFilters();
        $category ??= $filters['category'];
        $search ??= $filters['search'];
        $pageNumber ??= $filters['page'];
        $limit ??= $this->resolveContextLimit();

        $detailBase ??= app(DatasetDetailPageService::class)->detailBaseForType($typeModel);

        $query = app(DatasetArchiveQueryFactory::class)
            ->make($typeModel, $category, $search, $visibility)
            ->with(['translations', 'categories', 'visibilityRanges']);

        $allowedOrderColumns = ['sort_order', 'created_at', 'slug'];
        $orderColumn = in_array($orderBy, $allowedOrderColumns, true) ? $orderBy : 'created_at';
        $direction = in_array(strtolower($direction), ['asc', 'desc'], true) ? $direction : 'desc';

        $query->orderBy($orderColumn, $direction);

        if ($limit !== null && $pageNumber !== null && $pageNumber > 1) {
            $query->offset(($pageNumber - 1) * $limit);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $defaultLocale = config('app.locale', 'en');

        return $query->get()->map(function (CmsDataset $dataset) use ($locale, $defaultLocale, $typeModel, $detailBase): DatasetObject {
            $translation = $dataset->translations->firstWhere('locale', $locale)
                ?? $dataset->translations->firstWhere('locale', $defaultLocale);
            $translationData = $this->resolveTranslationData($translation?->data ?? [], $translation?->excerpt, $translation?->content);

            $categories = $dataset->categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ])->all();

            return new DatasetObject(
                fields: [
                    'id' => $dataset->id,
                    'type' => $typeModel->code,
                    'slug' => $dataset->slug,
                    'title' => $translation?->title ?? '',
                    'visibility_mode' => $dataset->visibility_mode,
                    'is_visible_now' => app(DatasetVisibilityService::class)->isVisibleNow($dataset),
                ],
                config: $dataset->config ?? [],
                data: $translationData,
                categories: $categories,
                detailBase: $detailBase,
            );
        })->all();
    }

    private function resolveContextLimit(): ?int
    {
        if (! app()->bound('cms.dataset_limit')) {
            return null;
        }

        $limit = app('cms.dataset_limit');

        return is_numeric($limit) && (int) $limit > 0
            ? (int) $limit
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveTranslationData(array $data, ?string $excerpt, ?string $content): array
    {
        if ($excerpt !== null && ! array_key_exists('excerpt', $data)) {
            $data['excerpt'] = $excerpt;
        }

        if ($content !== null && ! array_key_exists('content', $data)) {
            $data['content'] = $content;
        }

        return $data;
    }
}
