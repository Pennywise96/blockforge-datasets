<?php

namespace Blockforge\Datasets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsDatasetCategory extends Model
{
    protected $table = 'bf_dataset_categories';

    protected $fillable = [
        'type_id',
        'parent_id',
        'name',
        'slug',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CmsDatasetType::class, 'type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CmsDatasetCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CmsDatasetCategory::class, 'parent_id')->orderBy('sort_order');
    }

    public function datasets(): BelongsToMany
    {
        return $this->belongsToMany(CmsDataset::class, 'bf_dataset_category_dataset', 'category_id', 'dataset_id');
    }
}
