<?php

namespace Blockforge\Datasets\ViewHelpers;

use Blockforge\Cms\ViewHelpers\ViewHelper;
use Blockforge\Datasets\Elements\DatasetObject;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetType;

class DatasetDetailViewHelper extends ViewHelper
{
    public function render(string $type, string $as = 'item', ?string $slug = null): string
    {
        $locale = app()->bound('cms.locale') ? app('cms.locale') : app()->getLocale();
        $item = $this->fetchItem($locale, $type, $slug);

        if ($item === null) {
            return '';
        }

        return $this->renderChildren([$as => $item, 'item' => $item]);
    }

    private function fetchItem(string $locale, string $type, ?string $slug): ?DatasetObject
    {
        $entrySlug = $slug ?? (app()->bound('cms.dataset_slug') ? app('cms.dataset_slug') : null);

        if ($entrySlug === null) {
            return null;
        }

        $typeModel = CmsDatasetType::query()->where('slug', $type)->first();

        if ($typeModel === null) {
            return null;
        }

        $dataset = CmsDataset::query()
            ->where('type_id', $typeModel->id)
            ->where('slug', $entrySlug)
            ->where('status', 'published')
            ->with(['translations', 'categories'])
            ->first();

        if ($dataset === null) {
            abort(404);
        }

        $defaultLocale = config('app.locale', 'en');
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
        );
    }
}
