<?php

namespace Blockforge\Datasets\Elements;

class DatasetContextObject
{
    private const KEEP = '__bf_keep__';

    public function __construct(
        private string $type,
        private ?string $listUrl,
        private ?string $currentCategory = null,
        private int $currentPage = 1,
        private ?string $currentSearch = null,
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function listUrl(): ?string
    {
        return $this->listUrl;
    }

    public function currentUrl(): ?string
    {
        return $this->url();
    }

    public function search(): string
    {
        return $this->currentSearch ?? '';
    }

    public function category(): ?string
    {
        return $this->currentCategory;
    }

    public function page(): int
    {
        return $this->currentPage;
    }

    public function hasFilters(): bool
    {
        return $this->currentCategory !== null
            || $this->currentPage > 1
            || $this->currentSearch !== null;
    }

    public function url(
        mixed $category = self::KEEP,
        mixed $page = self::KEEP,
        mixed $search = self::KEEP,
    ): ?string {
        if ($this->listUrl === null) {
            return null;
        }

        $categoryChanged = $category !== self::KEEP;
        $searchChanged = $search !== self::KEEP;

        $resolvedCategory = $category === self::KEEP
            ? $this->currentCategory
            : $this->normalizeString($category);

        $resolvedSearch = $search === self::KEEP
            ? $this->currentSearch
            : $this->normalizeString($search);

        $resolvedPage = $page === self::KEEP
            ? $this->currentPage
            : $this->normalizePage($page);

        if (($categoryChanged || $searchChanged) && $page === self::KEEP) {
            $resolvedPage = 1;
        }

        $url = rtrim($this->listUrl, '/');

        if ($resolvedCategory !== null) {
            $url .= '/category/'.$resolvedCategory;
        }

        if ($resolvedPage > 1) {
            $url .= '/page/'.$resolvedPage;
        }

        if ($resolvedSearch !== null) {
            $url .= '?'.http_build_query(['q' => $resolvedSearch]);
        }

        return $url;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizePage(mixed $value): int
    {
        if (is_numeric($value) && (int) $value > 1) {
            return (int) $value;
        }

        return 1;
    }
}
