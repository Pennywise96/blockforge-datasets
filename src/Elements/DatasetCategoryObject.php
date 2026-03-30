<?php

namespace Blockforge\Datasets\Elements;

class DatasetCategoryObject
{
    public function __construct(
        private array $fields = [],
        private ?string $detailBase = null,
        private ?string $activeSlug = null,
        private ?string $search = null,
    ) {
        $this->fields = array_merge([
            'id' => null,
            'name' => '',
            'slug' => '',
            'count' => null,
        ], $fields);
    }

    public function __get(string $name): mixed
    {
        return $this->fields[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->fields);
    }

    public function isActive(): bool
    {
        $slug = $this->fields['slug'] ?? null;

        return is_string($slug) && $slug !== '' && $slug === $this->activeSlug;
    }

    public function url(): ?string
    {
        $slug = $this->fields['slug'] ?? null;

        if (! is_string($slug) || $slug === '' || $this->detailBase === null) {
            return null;
        }

        $url = rtrim($this->detailBase, '/').'/category/'.$slug;

        if (is_string($this->search) && $this->search !== '') {
            $url .= '?'.http_build_query(['q' => $this->search]);
        }

        return $url;
    }
}
