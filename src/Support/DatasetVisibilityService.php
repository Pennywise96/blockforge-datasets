<?php

namespace Blockforge\Datasets\Support;

use Blockforge\Datasets\Models\CmsDataset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DatasetVisibilityService
{
    public function applyVisibleNow(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now()->utc();

        return $query->where(function (Builder $builder) use ($now): void {
            $builder->where('visibility_mode', 'always')
                ->orWhere(function (Builder $scheduledQuery) use ($now): void {
                    $scheduledQuery->where('visibility_mode', 'scheduled')
                        ->whereHas('visibilityRanges', function (Builder $rangeQuery) use ($now): void {
                            $rangeQuery
                                ->where(function (Builder $startsAtQuery) use ($now): void {
                                    $startsAtQuery->whereNull('starts_at')
                                        ->orWhere('starts_at', '<=', $now);
                                })
                                ->where(function (Builder $endsAtQuery) use ($now): void {
                                    $endsAtQuery->whereNull('ends_at')
                                        ->orWhere('ends_at', '>=', $now);
                                });
                        });
                });
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $ranges
     * @return array<int, array{starts_at: string|null, ends_at: string|null}>
     */
    public function normalizeRanges(array $ranges): array
    {
        $normalized = collect($ranges)
            ->map(function (mixed $range): ?array {
                if (! is_array($range)) {
                    return null;
                }

                $startsAt = $this->normalizeDateTime($range['starts_at'] ?? null);
                $endsAt = $this->normalizeDateTime($range['ends_at'] ?? null);

                if ($startsAt !== null && $endsAt !== null && $startsAt > $endsAt) {
                    [$startsAt, $endsAt] = [$endsAt, $startsAt];
                }

                if ($startsAt === null && $endsAt === null) {
                    return null;
                }

                return [
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];
            })
            ->filter()
            ->sort(function (array $left, array $right): int {
                if ($left['starts_at'] === null && $right['starts_at'] === null) {
                    return 0;
                }

                if ($left['starts_at'] === null) {
                    return -1;
                }

                if ($right['starts_at'] === null) {
                    return 1;
                }

                return $left['starts_at']->getTimestamp() <=> $right['starts_at']->getTimestamp();
            })
            ->values()
            ->all();

        $merged = [];

        foreach ($normalized as $range) {
            if ($merged === []) {
                $merged[] = $range;

                continue;
            }

            $lastIndex = count($merged) - 1;
            $last = $merged[$lastIndex];

            if (! $this->rangesTouchOrOverlap($last, $range)) {
                $merged[] = $range;

                continue;
            }

            $merged[$lastIndex] = [
                'starts_at' => $this->minDateTime($last['starts_at'], $range['starts_at']),
                'ends_at' => $this->maxDateTime($last['ends_at'], $range['ends_at']),
            ];
        }

        return array_map(fn (array $range) => [
            'starts_at' => $range['starts_at']?->toISOString(),
            'ends_at' => $range['ends_at']?->toISOString(),
        ], $merged);
    }

    public function isVisibleNow(CmsDataset $dataset, ?Carbon $now = null): bool
    {
        $now ??= now()->utc();

        return match ($dataset->visibility_mode) {
            'always' => true,
            'scheduled' => $dataset->visibilityRanges->contains(
                fn ($range) => ($range->starts_at === null || $range->starts_at->lte($now))
                    && ($range->ends_at === null || $range->ends_at->gte($now))
            ),
            default => false,
        };
    }

    public function labelFor(CmsDataset $dataset, ?Carbon $now = null): string
    {
        $now ??= now()->utc();

        return match ($dataset->visibility_mode) {
            'always' => 'Always active',
            'scheduled' => $this->isVisibleNow($dataset, $now) ? 'Active now' : 'Scheduled',
            default => 'Disabled',
        };
    }

    private function normalizeDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->utc();
    }

    /**
     * @param  array{starts_at: Carbon|null, ends_at: Carbon|null}  $left
     * @param  array{starts_at: Carbon|null, ends_at: Carbon|null}  $right
     */
    private function rangesTouchOrOverlap(array $left, array $right): bool
    {
        if ($left['ends_at'] === null || $right['starts_at'] === null) {
            return true;
        }

        return $left['ends_at']->copy()->addSecond()->gte($right['starts_at']);
    }

    private function minDateTime(?Carbon $left, ?Carbon $right): ?Carbon
    {
        if ($left === null || $right === null) {
            return null;
        }

        return $left->lte($right) ? $left : $right;
    }

    private function maxDateTime(?Carbon $left, ?Carbon $right): ?Carbon
    {
        if ($left === null || $right === null) {
            return null;
        }

        return $left->gte($right) ? $left : $right;
    }
}
