<?php

namespace Blockforge\Datasets\Support;

use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Database\Eloquent\Builder;

class DatasetArchiveQueryFactory
{
    public function make(
        CmsDatasetType $type,
        ?string $category = null,
        ?string $search = null,
        string $status = 'published',
    ): Builder {
        $query = CmsDataset::query()
            ->where('type_id', $type->id)
            ->where('status', $status);

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

        return $query;
    }
}
