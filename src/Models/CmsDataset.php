<?php

namespace Blockforge\Datasets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsDataset extends Model
{
    protected $table = 'bf_datasets';

    protected $fillable = [
        'type_id',
        'slug',
        'date',
        'status',
        'sort_order',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sort_order' => 'integer',
            'config' => 'array',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CmsDatasetType::class, 'type_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CmsDatasetTranslation::class, 'dataset_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CmsDatasetCategory::class, 'bf_dataset_category_dataset', 'dataset_id', 'category_id');
    }
}
