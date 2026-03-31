<?php

namespace Blockforge\Datasets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsDatasetTranslation extends Model
{
    protected $table = 'bf_dataset_translations';

    protected $fillable = [
        'dataset_id',
        'locale',
        'title',
        'field_values',
    ];

    protected function casts(): array
    {
        return [
            'field_values' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(CmsDataset::class, 'dataset_id');
    }
}
