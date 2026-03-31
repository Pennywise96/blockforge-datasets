<?php

namespace Blockforge\Datasets\Support;

use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Database\Eloquent\Builder;

class DatasetArchiveQueryFactory
{
    public function __construct(
        private readonly DatasetVisibilityService $visibilityService,
    ) {}

    public function make(
        CmsDatasetType $type,
        ?string $category = null,
        ?string $search = null,
        string $visibility = 'visible',
    ): Builder {
        $query = CmsDataset::query()
            ->where('type_id', $type->id);

        $this->applyVisibilityFilter($query, $visibility);

        if ($category !== null) {
            $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $category));
        }

        if ($search !== null) {
            $searchTerm = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

            $query->whereHas('translations', function (Builder $translationQuery) use ($searchTerm): void {
                $translationQuery->where(function (Builder $textQuery) use ($searchTerm): void {
                    $textQuery->where('title', 'like', $searchTerm)
                        ->orWhere('field_values->excerpt', 'like', $searchTerm)
                        ->orWhere('field_values->content', 'like', $searchTerm);
                });
            });
        }

        return $query;
    }

    private function applyVisibilityFilter(Builder $query, string $visibility): void
    {
        match ($visibility) {
            'disabled' => $query->where('visibility_mode', 'disabled'),
            'always' => $query->where('visibility_mode', 'always'),
            'scheduled' => $query->where('visibility_mode', 'scheduled'),
            'all' => null,
            default => $this->visibilityService->applyVisibleNow($query),
        };
    }
}
