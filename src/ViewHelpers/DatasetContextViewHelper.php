<?php

namespace Blockforge\Datasets\ViewHelpers;

use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\ViewHelpers\ViewHelper;
use Blockforge\Datasets\Elements\DatasetContextObject;
use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\Support\DatasetDetailPageService;
use Blockforge\Datasets\Support\DatasetTypeResolver;

class DatasetContextViewHelper extends ViewHelper
{
    public function render(mixed $type = null, string $as = 'dataset'): string
    {
        $typeModel = $this->resolveType($type);

        if (! $typeModel instanceof CmsDatasetType) {
            return '';
        }

        $context = new DatasetContextObject(
            type: $typeModel->slug,
            listUrl: app(DatasetDetailPageService::class)->detailBaseForType($typeModel),
            currentCategory: $this->resolveCurrentCategory(),
            currentPage: $this->resolveCurrentPage(),
            currentSearch: $this->resolveCurrentSearch(),
        );

        $archivePage = $this->resolveArchivePage();

        return $this->withScopedBindings(
            [
                'cms.dataset_type' => $typeModel,
                'cms.dataset_list_page' => $archivePage,
            ],
            fn () => $this->renderChildren([
                'dataset' => $context,
                $as => $context,
            ]),
        );
    }

    private function resolveType(mixed $type = null): ?CmsDatasetType
    {
        $resolved = app(DatasetTypeResolver::class)->resolve($type);

        if ($resolved instanceof CmsDatasetType) {
            return $resolved;
        }

        $archivePage = $this->resolveArchivePage();

        if (! $archivePage instanceof CmsPage) {
            return null;
        }

        return app(DatasetDetailPageService::class)
            ->mappingForArchivePage($archivePage, $this->accessiblePageStatuses())
            ?->datasetType;
    }

    private function resolveArchivePage(): ?CmsPage
    {
        if (app()->bound('cms.dataset_list_page')) {
            $page = app('cms.dataset_list_page');

            if ($page instanceof CmsPage) {
                return $page;
            }
        }

        if (! app()->bound(CmsPage::class)) {
            return null;
        }

        $page = app(CmsPage::class);

        if (! $page instanceof CmsPage) {
            return null;
        }

        $mapping = app(DatasetDetailPageService::class)
            ->mappingForArchivePage($page, $this->accessiblePageStatuses());

        return $mapping !== null ? $page : null;
    }

    private function resolveCurrentCategory(): ?string
    {
        $filters = app()->bound('cms.dataset_filters') && is_array(app('cms.dataset_filters'))
            ? app('cms.dataset_filters')
            : [];

        return is_string($filters['category'] ?? null) && trim($filters['category']) !== ''
            ? trim($filters['category'])
            : null;
    }

    private function resolveCurrentPage(): int
    {
        $filters = app()->bound('cms.dataset_filters') && is_array(app('cms.dataset_filters'))
            ? app('cms.dataset_filters')
            : [];

        return is_numeric($filters['page'] ?? null) && (int) $filters['page'] > 1
            ? (int) $filters['page']
            : 1;
    }

    private function resolveCurrentSearch(): ?string
    {
        $search = request()->query('q');

        return is_string($search) && trim($search) !== ''
            ? trim($search)
            : null;
    }

    /** @return string[] */
    private function accessiblePageStatuses(): array
    {
        $statuses = ['published', 'hidden'];

        if (auth()->check()) {
            $statuses[] = 'draft';
        }

        return $statuses;
    }

    /**
     * @param  array<string, mixed>  $bindings
     */
    private function withScopedBindings(array $bindings, callable $callback): string
    {
        $previous = [];

        foreach ($bindings as $key => $value) {
            $previous[$key] = [
                'bound' => app()->bound($key),
                'value' => app()->bound($key) ? app($key) : null,
            ];

            if ($value !== null) {
                app()->instance($key, $value);
            }
        }

        try {
            return $callback();
        } finally {
            foreach ($bindings as $key => $value) {
                app()->forgetInstance($key);

                if ($previous[$key]['bound']) {
                    app()->instance($key, $previous[$key]['value']);
                }
            }
        }
    }
}
