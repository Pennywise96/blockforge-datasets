<?php

namespace Blockforge\Datasets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsDatasetVisibilityRange extends Model
{
    public $timestamps = false;

    protected $table = 'bf_dataset_visibility_ranges';

    protected $fillable = [
        'dataset_id',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(CmsDataset::class, 'dataset_id');
    }
}
