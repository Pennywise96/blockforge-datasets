<?php

namespace Blockforge\Datasets\Routing;

use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Routing\PageRouteFallbackResolver;
use Blockforge\Cms\Support\PageSlugService;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Support\DatasetDetailPageService;
use Illuminate\Database\Eloquent\Builder;

class CanonicalDatasetDetailPageResolver implements PageRouteFallbackResolver
{
    public function resolve(
        CmsSite $site,
        string $slug,
        int $activeLocaleId,
        ?int $defaultLocaleId,
        string $translationMode,
        bool $guest,
    ): ?CmsPage {
        if (! str_contains($slug, '/')) {
            return null;
        }

        $lastSlash = strrpos($slug, '/');
        $archiveSlug = substr($slug, 0, $lastSlash);
        $datasetSlug = substr($slug, $lastSlash + 1);

        if ($archiveSlug === '' || $datasetSlug === '') {
            return null;
        }

        $archivePage = $this->resolveArchivePage($site, $archiveSlug, $activeLocaleId, $defaultLocaleId, $translationMode, $guest);

        if ($archivePage === null) {
            return null;
        }

        $mapping = app(DatasetDetailPageService::class)->mappingForArchivePage($archivePage, $this->accessibleStatuses());

        if ($mapping === null || $mapping->datasetType === null || $mapping->page === null) {
            return null;
        }

        $datasetExists = CmsDataset::query()
            ->where('type_id', $mapping->dataset_type_id)
            ->where('slug', $datasetSlug)
            ->where('status', 'published')
            ->exists();

        if (! $datasetExists) {
            return null;
        }

        app()->instance('cms.dataset_slug', $datasetSlug);
        app()->instance('cms.dataset_type', $mapping->datasetType);
        app()->instance('cms.dataset_list_page', $archivePage);
        app()->instance('cms.dataset_detail_page', $mapping->page);

        return $mapping->page;
    }

    private function resolveArchivePage(
        CmsSite $site,
        string $archiveSlug,
        int $activeLocaleId,
        ?int $defaultLocaleId,
        string $translationMode,
        bool $guest,
    ): ?CmsPage {
        $localeIds = array_values(array_filter(array_unique([$activeLocaleId, $defaultLocaleId])));
        $pageSlugService = app(PageSlugService::class);

        $pages = CmsPage::query()
            ->where('site_id', $site->id)
            ->whereIn('status', $this->accessibleStatuses())
            ->where(function (Builder $query) use ($archiveSlug, $localeIds): void {
                $query->where('slug', $archiveSlug)
                    ->orWhereHas('translations', fn (Builder $translationQuery) => $translationQuery
                        ->whereIn('site_locale_id', $localeIds)
                        ->where('slug', $archiveSlug));
            })
            ->with([
                'translations' => fn ($query) => $query->whereIn('site_locale_id', $localeIds),
            ])
            ->get(['id', 'site_id', 'slug', 'status', 'parent_id']);

        return $pages->first(
            fn (CmsPage $page) => $pageSlugService->publicSlugMatches($page, $archiveSlug, $activeLocaleId, $defaultLocaleId, $translationMode, $guest)
        );
    }

    /** @return string[] */
    private function accessibleStatuses(): array
    {
        $statuses = ['published', 'hidden'];

        if (auth()->check()) {
            $statuses[] = 'draft';
        }

        return $statuses;
    }
}
