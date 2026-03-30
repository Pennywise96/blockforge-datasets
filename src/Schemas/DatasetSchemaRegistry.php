<?php

namespace Blockforge\Datasets\Schemas;

use Blockforge\Cms\Config\Page;

class DatasetSchemaRegistry
{
    /** @var array<string, array<string, DatasetSchema>> */
    private array $resolved = [];

    public function __construct(
        private readonly DatasetSchemaLoader $loader,
    ) {}

    /**
     * @return array<string, DatasetSchema>
     */
    public function all(?Page $page = null): array
    {
        $page ??= app()->bound(Page::class) ? app(Page::class) : null;

        if (! $page instanceof Page) {
            return [];
        }

        $cacheKey = $page->getHandle().'|'.$page->getDomain().'|'.($page->getPathPrefix() ?? '');

        return $this->resolved[$cacheKey] ??= $this->loader->loadForPage($page);
    }

    public function find(string $code, ?Page $page = null): ?DatasetSchema
    {
        return $this->all($page)[$code] ?? null;
    }
}
