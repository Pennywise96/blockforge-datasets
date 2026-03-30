<?php

use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Support\DatasetVisibilityService;
use Illuminate\Support\Carbon;

it('merges overlapping and adjacent visibility ranges', function (): void {
    $ranges = app(DatasetVisibilityService::class)->normalizeRanges([
        [
            'starts_at' => '2026-03-01T10:00:00+00:00',
            'ends_at' => '2026-03-05T10:00:00+00:00',
        ],
        [
            'starts_at' => '2026-03-05T10:00:00+00:00',
            'ends_at' => '2026-03-06T10:00:00+00:00',
        ],
        [
            'starts_at' => '2026-03-10T10:00:00+00:00',
            'ends_at' => null,
        ],
    ]);

    expect($ranges)->toHaveCount(2)
        ->and($ranges[0]['starts_at'])->toBe('2026-03-01T10:00:00.000000Z')
        ->and($ranges[0]['ends_at'])->toBe('2026-03-06T10:00:00.000000Z')
        ->and($ranges[1]['starts_at'])->toBe('2026-03-10T10:00:00.000000Z')
        ->and($ranges[1]['ends_at'])->toBeNull();
});

it('computes current visibility for disabled, always, and scheduled datasets', function (): void {
    $service = app(DatasetVisibilityService::class);
    $now = Carbon::parse('2026-03-04T12:00:00+00:00');

    $disabled = new CmsDataset(['visibility_mode' => 'disabled']);
    $always = new CmsDataset(['visibility_mode' => 'always']);
    $scheduled = new CmsDataset(['visibility_mode' => 'scheduled']);
    $scheduled->setRelation('visibilityRanges', collect([
        (object) [
            'starts_at' => Carbon::parse('2026-03-01T00:00:00+00:00'),
            'ends_at' => Carbon::parse('2026-03-05T00:00:00+00:00'),
        ],
    ]));

    expect($service->isVisibleNow($disabled, $now))->toBeFalse()
        ->and($service->isVisibleNow($always, $now))->toBeTrue()
        ->and($service->isVisibleNow($scheduled, $now))->toBeTrue();
});
