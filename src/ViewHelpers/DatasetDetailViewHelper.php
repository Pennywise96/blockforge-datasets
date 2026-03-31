<?php

namespace Blockforge\Datasets\ViewHelpers;

use Blockforge\Cms\ViewHelpers\ViewHelper;
use Blockforge\Datasets\Elements\DatasetObject;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Support\DatasetTypeResolver;
use Blockforge\Datasets\Support\DatasetVisibilityService;

class DatasetDetailViewHelper extends ViewHelper
{
    public function render(?string $type = null, string $as = 'item', ?string $slug = null): string
    {
        $locale = app()->bound('cms.locale') ? app('cms.locale') : app()->getLocale();
        $item = $this->fetchItem($locale, $type, $slug);

        if ($item === null) {
            return '';
        }

        return $this->renderChildren([$as => $item, 'item' => $item]);
    }

    private function fetchItem(string $locale, ?string $type, ?string $slug): ?DatasetObject
    {
        $entrySlug = $slug ?? (app()->bound('cms.dataset_slug') ? app('cms.dataset_slug') : null);

        if ($entrySlug === null) {
            return null;
        }

        $typeModel = app(DatasetTypeResolver::class)->resolve($type);

        if ($typeModel === null) {
            return null;
        }

        $dataset = CmsDataset::query()
            ->where('type_id', $typeModel->id)
            ->where('slug', $entrySlug)
            ->with(['translations', 'categories', 'visibilityRanges'])
            ->first();

        if ($dataset === null || ! app(DatasetVisibilityService::class)->isVisibleNow($dataset)) {
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
                'type' => $typeModel->code,
                'slug' => $dataset->slug,
                'title' => $translation?->title ?? '',
                'visibility_mode' => $dataset->visibility_mode,
                'is_visible_now' => true,
            ],
            fieldValues: $dataset->field_values ?? [],
            translatedFieldValues: $translation?->field_values ?? [],
            categories: $categories,
        );
    }
}
