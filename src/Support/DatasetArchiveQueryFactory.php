<?php

namespace Blockforge\Datasets\Support;

use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\Schemas\DatasetSchemaRegistry;
use Illuminate\Database\Eloquent\Builder;

class DatasetArchiveQueryFactory
{
    public function __construct(
        private readonly DatasetVisibilityService $visibilityService,
        private readonly DatasetSchemaRegistry $schemaRegistry,
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
            $searchableTranslationPaths = $this->resolveSearchableTranslationPaths($type);

            $query->whereHas('translations', function (Builder $translationQuery) use ($searchTerm, $searchableTranslationPaths): void {
                $translationQuery->where(function (Builder $textQuery) use ($searchTerm, $searchableTranslationPaths): void {
                    $textQuery->where('title', 'like', $searchTerm);

                    foreach ($searchableTranslationPaths as $path) {
                        $textQuery->orWhere("field_values->{$path}", 'like', $searchTerm);
                    }
                });
            });
        }

        return $query;
    }

    /**
     * @return string[]
     */
    private function resolveSearchableTranslationPaths(CmsDatasetType $type): array
    {
        return $this->schemaRegistry
            ->find($type->code)
            ?->searchableTranslationFieldPaths()
            ?? [];
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
