<?php

namespace Blockforge\Datasets\Support;

use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Support\CmsUrlResolver;
use Blockforge\Cms\Support\PageSlugService;
use Blockforge\Datasets\Models\CmsDatasetDetailPage;
use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class DatasetDetailPageService
{
    /** @return array<string, mixed> */
    public function settingsForPage(CmsPage $page): array
    {
        $mapping = $this->mappingForPage($page);

        return [
            'is_dataset_detail_page' => $mapping !== null,
            'dataset_detail_type_id' => $mapping?->dataset_type_id,
            'dataset_detail_type_code' => $mapping?->datasetType?->code,
            'dataset_detail_type_name' => $mapping?->datasetType?->name,
        ];
    }

    public function mappingForPage(CmsPage $page): ?CmsDatasetDetailPage
    {
        return CmsDatasetDetailPage::query()
            ->where('page_id', $page->id)
            ->with(['datasetType', 'archivePage.translations', 'page.parent.translations', 'page.translations'])
            ->first();
    }

    public function mappingForArchivePage(CmsPage $archivePage, array $pageStatuses): ?CmsDatasetDetailPage
    {
        $mappings = CmsDatasetDetailPage::query()
            ->where('site_id', $archivePage->site_id)
            ->where(function (Builder $query) use ($archivePage): void {
                $query->where('archive_page_id', $archivePage->id)
                    ->orWhereHas('page', fn (Builder $pageQuery) => $pageQuery->where('parent_id', $archivePage->id));
            })
            ->whereHas('page', fn (Builder $query) => $query
                ->whereIn('status', $pageStatuses))
            ->with([
                'datasetType',
                'page' => fn ($query) => $query->whereIn('status', $pageStatuses),
                'archivePage.translations',
                'page.translations',
                'page.parent.translations',
            ])
            ->get()
            ->filter(fn (CmsDatasetDetailPage $mapping) => $mapping->page !== null)
            ->values();

        if ($mappings->count() !== 1) {
            return null;
        }

        return $mappings->first();
    }

    public function mappingForTypeInSite(int $siteId, int $datasetTypeId): ?CmsDatasetDetailPage
    {
        return CmsDatasetDetailPage::query()
            ->where('site_id', $siteId)
            ->where('dataset_type_id', $datasetTypeId)
            ->with(['archivePage.translations', 'page.parent.translations', 'page.translations', 'datasetType'])
            ->first();
    }

    public function detailBaseForType(CmsDatasetType $datasetType): ?string
    {
        $site = app()->bound(CmsSite::class) ? app(CmsSite::class) : null;

        if (! $site instanceof CmsSite) {
            return null;
        }

        $mapping = $this->mappingForTypeInSite($site->id, $datasetType->id);
        $parentPage = $mapping?->page?->parent;

        if (! $parentPage instanceof CmsPage || ! in_array($mapping?->page?->status, $this->accessiblePageStatuses(), true)) {
            return null;
        }

        $slug = app(PageSlugService::class)->resolvedPublicSlug($parentPage);

        return app(CmsUrlResolver::class)->buildUrl($slug);
    }

    public function updatePageSettings(CmsPage $page, bool $isDatasetDetailPage, ?int $datasetTypeId): ?CmsDatasetDetailPage
    {
        $mapping = CmsDatasetDetailPage::query()->where('page_id', $page->id)->first();

        if (! $isDatasetDetailPage) {
            $mapping?->delete();

            return null;
        }

        if (! $page->isStandardDoktype()) {
            throw ValidationException::withMessages([
                'is_dataset_detail_page' => ['Only standard pages can be used as dataset detail pages.'],
            ]);
        }

        if ($page->parent_id === null) {
            throw ValidationException::withMessages([
                'is_dataset_detail_page' => ['Dataset detail pages must live under an archive page.'],
            ]);
        }

        $datasetType = $datasetTypeId !== null
            ? CmsDatasetType::query()->find($datasetTypeId)
            : null;

        if ($datasetType === null) {
            throw ValidationException::withMessages([
                'dataset_detail_type_id' => ['Please select a dataset type.'],
            ]);
        }

        $typeConflict = CmsDatasetDetailPage::query()
            ->where('site_id', $page->site_id)
            ->where('dataset_type_id', $datasetType->id)
            ->when($mapping !== null, fn (Builder $query) => $query->where('page_id', '!=', $page->id))
            ->exists();

        if ($typeConflict) {
            throw ValidationException::withMessages([
                'dataset_detail_type_id' => ['This dataset type already has a canonical detail page in this site.'],
            ]);
        }

        $archiveConflict = CmsDatasetDetailPage::query()
            ->where('site_id', $page->site_id)
            ->whereHas('page', fn (Builder $query) => $query->where('parent_id', $page->parent_id))
            ->when($mapping !== null, fn (Builder $query) => $query->where('page_id', '!=', $page->id))
            ->exists();

        if ($archiveConflict) {
            throw ValidationException::withMessages([
                'is_dataset_detail_page' => ['This archive page already owns a canonical dataset detail page.'],
            ]);
        }

        $mapping = CmsDatasetDetailPage::query()->updateOrCreate(
            ['page_id' => $page->id],
            [
                'site_id' => $page->site_id,
                'archive_page_id' => $page->parent_id,
                'dataset_type_id' => $datasetType->id,
            ],
        );

        if (! $page->nav_hidden) {
            $page->update(['nav_hidden' => true]);
        }

        return $this->mappingForPage($page->fresh());
    }

    public function syncMappingForPage(CmsPage $page): void
    {
        $mapping = CmsDatasetDetailPage::query()->where('page_id', $page->id)->first();

        if (! $mapping instanceof CmsDatasetDetailPage) {
            return;
        }

        if (! $page->isStandardDoktype() || $page->parent_id === null) {
            $mapping->delete();

            return;
        }

        $typeConflict = CmsDatasetDetailPage::query()
            ->where('site_id', $page->site_id)
            ->where('dataset_type_id', $mapping->dataset_type_id)
            ->where('page_id', '!=', $page->id)
            ->exists();

        if ($typeConflict) {
            throw ValidationException::withMessages([
                'dataset_detail_type_id' => ['This dataset type already has a canonical detail page in this site.'],
            ]);
        }

        $archiveConflict = CmsDatasetDetailPage::query()
            ->where('site_id', $page->site_id)
            ->where('archive_page_id', $page->parent_id)
            ->where('page_id', '!=', $page->id)
            ->exists();

        if ($archiveConflict) {
            throw ValidationException::withMessages([
                'parent_id' => ['This archive page already owns a canonical dataset detail page.'],
            ]);
        }

        if ((int) $mapping->site_id === (int) $page->site_id && (int) $mapping->archive_page_id === (int) $page->parent_id) {
            return;
        }

        $mapping->update([
            'site_id' => $page->site_id,
            'archive_page_id' => $page->parent_id,
        ]);
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
}
