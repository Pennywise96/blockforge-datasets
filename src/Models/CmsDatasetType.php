<?php

namespace Blockforge\Datasets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsDatasetType extends Model
{
    protected $table = 'bf_dataset_types';

    protected $fillable = [
        'name',
        'code',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(CmsDatasetCategory::class, 'type_id')->orderBy('sort_order');
    }

    public function datasets(): HasMany
    {
        return $this->hasMany(CmsDataset::class, 'type_id');
    }
}
