<?php

namespace Blockforge\Datasets\ViewHelpers;

use Blockforge\Cms\ViewHelpers\ViewHelper;
use Blockforge\Datasets\Elements\DatasetCategoryObject;
use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\Support\DatasetDetailPageService;
use Blockforge\Datasets\Support\DatasetTypeResolver;
use Blockforge\Datasets\Support\DatasetVisibilityService;
use Illuminate\Database\Eloquent\Builder;

class DatasetCategoriesViewHelper extends ViewHelper
{
    public function render(
        mixed $type = null,
        mixed $categories = null,
        string $as = 'categories',
        bool $onlyUsed = false,
        bool $withCount = false,
        string $sortBy = 'sort_order',
    ): string {
        $items = $this->fetchCategories($type, $categories, $onlyUsed, $withCount, $sortBy);

        return $this->renderChildren([
            'categories' => $items,
            $as => $items,
        ]);
    }

    /** @return DatasetCategoryObject[] */
    private function fetchCategories(
        mixed $type,
        mixed $categories,
        bool $onlyUsed,
        bool $withCount,
        string $sortBy,
    ): array {
        $typeModel = app(DatasetTypeResolver::class)->resolve($type);

        if (! $typeModel instanceof CmsDatasetType) {
            return [];
        }

        $detailBase = app(DatasetDetailPageService::class)->detailBaseForType($typeModel);
        $activeCategory = $this->resolveActiveCategory();
        $search = $this->resolveSearchTerm();
        [$categoryIds, $categorySlugs] = $this->normalizeCategoryFilter($categories);

        $query = CmsDatasetCategory::query()
            ->where('type_id', $typeModel->id);

        if ($categoryIds !== [] || $categorySlugs !== []) {
            $query->where(function (Builder $builder) use ($categoryIds, $categorySlugs): void {
                if ($categoryIds !== []) {
                    $builder->whereIn('id', $categoryIds);
                }

                if ($categorySlugs !== []) {
                    $method = $categoryIds !== [] ? 'orWhereIn' : 'whereIn';
                    $builder->{$method}('slug', $categorySlugs);
                }
            });
        }

        if ($withCount || $onlyUsed) {
            $query->withCount([
                'datasets as published_datasets_count' => function (Builder $builder) use ($typeModel): void {
                    $builder->where('type_id', $typeModel->id);
                    app(DatasetVisibilityService::class)->applyVisibleNow($builder);
                },
            ]);
        }

        if ($onlyUsed) {
            $query->whereHas('datasets', function (Builder $builder) use ($typeModel): void {
                $builder->where('type_id', $typeModel->id);
                app(DatasetVisibilityService::class)->applyVisibleNow($builder);
            });
        }

        $allowedSortColumns = ['sort_order', 'name', 'slug', 'created_at'];
        $sortColumn = in_array($sortBy, $allowedSortColumns, true) ? $sortBy : 'sort_order';

        return $query->orderBy($sortColumn)
            ->get()
            ->map(fn (CmsDatasetCategory $category) => new DatasetCategoryObject(
                fields: [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => ($withCount || $onlyUsed) ? (int) ($category->published_datasets_count ?? 0) : null,
                ],
                detailBase: $detailBase,
                activeSlug: $activeCategory,
                search: $search,
            ))
            ->all();
    }

    private function resolveActiveCategory(): ?string
    {
        $filters = app()->bound('cms.dataset_filters') && is_array(app('cms.dataset_filters'))
            ? app('cms.dataset_filters')
            : [];

        return is_string($filters['category'] ?? null) && $filters['category'] !== ''
            ? $filters['category']
            : null;
    }

    private function resolveSearchTerm(): ?string
    {
        $search = request()->query('q');

        return is_string($search) && trim($search) !== ''
            ? trim($search)
            : null;
    }

    /** @return array{0: int[], 1: string[]} */
    private function normalizeCategoryFilter(mixed $categories): array
    {
        if ($categories === null || $categories === '') {
            return [[], []];
        }

        $values = is_array($categories) ? $categories : [$categories];
        $ids = [];
        $slugs = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;

                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $slugs[] = trim($value);
            }
        }

        return [array_values(array_unique($ids)), array_values(array_unique($slugs))];
    }
}
