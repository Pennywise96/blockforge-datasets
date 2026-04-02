<?php

namespace Blockforge\Datasets\Models;

use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Models\CmsSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsDatasetDetailPage extends Model
{
    protected $table = 'bf_dataset_detail_pages';

    protected $fillable = [
        'site_id',
        'page_id',
        'archive_page_id',
        'dataset_type_id',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(CmsSite::class, 'site_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }

    public function archivePage(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'archive_page_id');
    }

    public function datasetType(): BelongsTo
    {
        return $this->belongsTo(CmsDatasetType::class, 'dataset_type_id');
    }
}
