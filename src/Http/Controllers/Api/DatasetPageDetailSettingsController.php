<?php

namespace Blockforge\Datasets\Http\Controllers\Api;

use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Datasets\Support\DatasetDetailPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DatasetPageDetailSettingsController
{
    public function __construct(
        private readonly DatasetDetailPageService $detailPageService,
    ) {}

    public function show(CmsPage $page): JsonResponse
    {
        Gate::authorize('view', $page);
        $this->abortUnlessCurrentSitePage($page);

        return response()->json($this->detailPageService->settingsForPage($page));
    }

    public function update(Request $request, CmsPage $page): JsonResponse
    {
        Gate::authorize('update', $page);
        $this->abortUnlessCurrentSitePage($page);

        $validated = $request->validate([
            'is_dataset_detail_page' => ['required', 'boolean'],
            'dataset_detail_type_id' => ['nullable', 'integer', 'exists:bf_dataset_types,id'],
        ]);

        $this->detailPageService->updatePageSettings(
            page: $page,
            isDatasetDetailPage: $validated['is_dataset_detail_page'],
            datasetTypeId: $validated['dataset_detail_type_id'] ?? null,
        );

        return response()->json($this->detailPageService->settingsForPage($page->fresh()));
    }

    private function abortUnlessCurrentSitePage(CmsPage $page): void
    {
        if (! app()->bound(CmsSite::class)) {
            return;
        }

        abort_if($page->site_id !== app(CmsSite::class)->id, 404);
    }
}
