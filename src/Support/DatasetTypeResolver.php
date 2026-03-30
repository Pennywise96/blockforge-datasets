<?php

namespace Blockforge\Datasets\Support;

use Blockforge\Datasets\Models\CmsDatasetType;

class DatasetTypeResolver
{
    public function resolve(mixed $type = null): ?CmsDatasetType
    {
        $candidate = $type;

        if ($candidate === null && app()->bound('cms.dataset_type')) {
            $candidate = app('cms.dataset_type');
        }

        if ($candidate instanceof CmsDatasetType) {
            return $candidate;
        }

        if (is_numeric($candidate)) {
            return CmsDatasetType::query()->find((int) $candidate);
        }

        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        return CmsDatasetType::query()->where('code', $candidate)->first();
    }
}
