<?php

namespace Blockforge\Datasets\Schemas;

use Blockforge\Cms\Config\Page;

class DatasetSchemaLoader
{
    /**
     * @return array<string, DatasetSchema>
     */
    public function loadForPage(?Page $page = null): array
    {
        $page ??= app()->bound(Page::class) ? app(Page::class) : null;

        if (! $page instanceof Page) {
            return [];
        }

        $resolved = [];
        $inline = [];

        foreach ($page->getDatasetSchemas() as $source) {
            if ($source instanceof DatasetSchema) {
                $source->validateFieldDefinitions($this->reservedFieldPaths());
                $inline[$source->getCode()] = $source;

                continue;
            }

            if (! is_string($source) || trim($source) === '') {
                continue;
            }

            foreach ($this->loadFromPath($source) as $schema) {
                $schema->validateFieldDefinitions($this->reservedFieldPaths());
                $resolved[$schema->getCode()] = $schema;
            }
        }

        return array_replace($resolved, $inline);
    }

    /**
     * @return DatasetSchema[]
     */
    private function loadFromPath(string $path): array
    {
        $absolutePath = public_path($path);

        if (is_dir($absolutePath)) {
            $schemas = [];

            foreach (glob($absolutePath.'/*/config.php') ?: [] as $configFile) {
                $schema = $this->requireSchemaFile($configFile);

                if ($schema instanceof DatasetSchema) {
                    $schemas[] = $schema;
                }
            }

            return $schemas;
        }

        $schema = $this->requireSchemaFile($absolutePath);

        return $schema instanceof DatasetSchema ? [$schema] : [];
    }

    private function requireSchemaFile(string $path): ?DatasetSchema
    {
        if (! file_exists($path)) {
            return null;
        }

        $schema = require $path;

        return $schema instanceof DatasetSchema ? $schema : null;
    }

    /**
     * @return string[]
     */
    private function reservedFieldPaths(): array
    {
        return [
            'title',
            'slug',
            'visibility_mode',
            'visibility_ranges',
            'categories',
        ];
    }
}
