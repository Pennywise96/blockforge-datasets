<?php

namespace Blockforge\Datasets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsDatasetTranslation extends Model
{
    protected $fillable = [
        'dataset_id',
        'locale',
        'title',
        'excerpt',
        'content',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(CmsDataset::class, 'dataset_id');
    }
}
