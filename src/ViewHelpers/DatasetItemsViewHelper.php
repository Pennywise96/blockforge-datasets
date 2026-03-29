<?php

namespace Blockforge\Datasets\ViewHelpers;

use Blockforge\Cms\ViewHelpers\ViewHelper;
use Blockforge\Datasets\Elements\DatasetObject;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Support\DatasetDetailPageService;
use Blockforge\Datasets\Support\DatasetTypeResolver;
use Illuminate\Database\Eloquent\Builder;

class DatasetItemsViewHelper extends ViewHelper
{
    public function render(
        ?string $type = null,
        string $as = 'item',
        string $iteration = 'iteration',
        ?int $limit = null,
        ?string $category = null,
        ?string $search = null,
        ?int $page = null,
        string $orderBy = 'date',
        string $direction = 'desc',
        string $status = 'published',
        ?string $detailBase = null,
    ): string {
        $locale = app()->bound('cms.locale') ? app('cms.locale') : app()->getLocale();
        $items = $this->fetchItems($locale, $detailBase, $type, $limit, $category, $search, $page, $orderBy, $direction, $status);

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
        ?int $page,
        string $orderBy,
        string $direction,
        string $status,
    ): array {
        $typeModel = app(DatasetTypeResolver::class)->resolve($type);

        if (! $typeModel) {
            return [];
        }

        $filters = $this->resolveContextFilters();
        $category ??= $filters['category'];
        $search ??= $filters['search'];
        $page ??= $filters['page'];

        $detailBase ??= app(DatasetDetailPageService::class)->detailBaseForType($typeModel);

        $query = CmsDataset::query()
            ->where('type_id', $typeModel->id)
            ->where('status', $status)
            ->with(['translations', 'categories']);

        if ($category !== null) {
            $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $category));
        }

        if ($search !== null) {
            $searchTerm = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

            $query->whereHas('translations', function (Builder $translationQuery) use ($searchTerm): void {
                $translationQuery->where(function (Builder $textQuery) use ($searchTerm): void {
                    $textQuery->where('title', 'like', $searchTerm)
                        ->orWhere('excerpt', 'like', $searchTerm)
                        ->orWhere('content', 'like', $searchTerm);
                });
            });
        }

        $allowedOrderColumns = ['date', 'sort_order', 'created_at'];
        $orderColumn = in_array($orderBy, $allowedOrderColumns, true) ? $orderBy : 'date';
        $direction = in_array(strtolower($direction), ['asc', 'desc'], true) ? $direction : 'desc';

        $query->orderBy($orderColumn, $direction);

        if ($limit !== null && $page !== null && $page > 1) {
            $query->offset(($page - 1) * $limit);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $defaultLocale = config('app.locale', 'en');

        return $query->get()->map(function (CmsDataset $dataset) use ($locale, $defaultLocale, $typeModel, $detailBase): DatasetObject {
            $translation = $dataset->translations->firstWhere('locale', $locale)
                ?? $dataset->translations->firstWhere('locale', $defaultLocale);

            $categories = $dataset->categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ])->all();

            return new DatasetObject(
                fields: [
                    'id' => $dataset->id,
                    'type' => $typeModel->slug,
                    'slug' => $dataset->slug,
                    'date' => $dataset->date,
                    'status' => $dataset->status,
                    'title' => $translation?->title ?? '',
                    'excerpt' => $translation?->excerpt,
                    'content' => $translation?->content,
                ],
                config: $dataset->config ?? [],
                data: $translation?->data ?? [],
                categories: $categories,
                detailBase: $detailBase,
            );
        })->all();
    }
}
