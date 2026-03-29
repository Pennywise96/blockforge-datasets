<?php

namespace Blockforge\Datasets\ViewHelpers;

use Blockforge\Cms\Models\CmsPage;
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
        string $orderBy = 'date',
        string $direction = 'desc',
        string $status = 'published',
        ?string $detailBase = null,
    ): string {
        $locale = app()->bound('cms.locale') ? app('cms.locale') : app()->getLocale();
        $items = $this->fetchItems($locale, $detailBase, $type, $limit, $category, $orderBy, $direction, $status);

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

    private function resolveLegacyDetailBase(): ?string
    {
        if (! app()->bound(CmsPage::class)) {
            return null;
        }

        $page = app(CmsPage::class);

        if ($page->detail_page_id === null) {
            return null;
        }

        $slug = $page->slug;

        return $slug === '/' ? '' : '/'.ltrim($slug, '/');
    }

    /** @return DatasetObject[] */
    private function fetchItems(
        string $locale,
        ?string $detailBase,
        ?string $type,
        ?int $limit,
        ?string $category,
        string $orderBy,
        string $direction,
        string $status,
    ): array {
        $typeModel = app(DatasetTypeResolver::class)->resolve($type);

        if (! $typeModel) {
            return [];
        }

        $detailBase ??= app(DatasetDetailPageService::class)->detailBaseForType($typeModel)
            ?? $this->resolveLegacyDetailBase();

        $query = CmsDataset::query()
            ->where('type_id', $typeModel->id)
            ->where('status', $status)
            ->with(['translations', 'categories']);

        if ($category !== null) {
            $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $category));
        }

        $allowedOrderColumns = ['date', 'sort_order', 'created_at'];
        $orderColumn = in_array($orderBy, $allowedOrderColumns, true) ? $orderBy : 'date';
        $direction = in_array(strtolower($direction), ['asc', 'desc'], true) ? $direction : 'desc';

        $query->orderBy($orderColumn, $direction);

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
