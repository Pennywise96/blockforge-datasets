<?php

namespace Blockforge\Datasets\Schemas;

use Blockforge\Cms\Fields\Field;
use Blockforge\Cms\Fields\Fieldset;
use Blockforge\Cms\Fields\PictureField;
use Blockforge\Cms\Fields\Repeater;
use Blockforge\Cms\Fields\Tabs;
use Blockforge\Cms\Models\CmsMediaItem;
use Blockforge\Cms\Support\NestedData;

class DatasetSchemaMediaNormalizer
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeConfig(?DatasetSchema $schema, array $config): array
    {
        if (! $schema instanceof DatasetSchema) {
            return $config;
        }

        return $this->normalizePayload($config, $schema->getFields(), false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeTranslationData(?DatasetSchema $schema, array $data): array
    {
        if (! $schema instanceof DatasetSchema) {
            return $data;
        }

        return $this->normalizePayload($data, $schema->getFields(), true);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function resolveConfig(?DatasetSchema $schema, array $config): array
    {
        if (! $schema instanceof DatasetSchema) {
            return $config;
        }

        return $this->resolvePayload($config, $schema->getFields(), false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveTranslationData(?DatasetSchema $schema, array $data): array
    {
        if (! $schema instanceof DatasetSchema) {
            return $data;
        }

        return $this->resolvePayload($data, $schema->getFields(), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Field[]  $fields
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload, array $fields, bool $translatable): array
    {
        foreach ($fields as $field) {
            if ($field instanceof Tabs) {
                foreach ($field->getTabs() as $tab) {
                    $payload = $this->normalizePayload($payload, $tab->getFields(), $translatable);
                }

                continue;
            }

            if ($field instanceof Fieldset) {
                $payload = $this->normalizePayload($payload, $field->getFields(), $translatable);

                continue;
            }

            if ($field instanceof Repeater) {
                $items = NestedData::get($payload, $field->getName(), []);

                if (! is_array($items)) {
                    continue;
                }

                foreach ($items as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $items[$index] = $this->normalizeRepeaterItem($item, $field->getFields(), $translatable);
                }

                $payload = NestedData::set($payload, $field->getName(), $items);

                continue;
            }

            if (! $field instanceof PictureField || $field->isTranslatable() !== $translatable) {
                continue;
            }

            if (! NestedData::has($payload, $field->getName())) {
                continue;
            }

            $payload = NestedData::set(
                $payload,
                $field->getName(),
                $this->normalizePictureFieldValue(
                    NestedData::get($payload, $field->getName()),
                    $field->isMultiple(),
                ),
            );
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Field[]  $fields
     * @return array<string, mixed>
     */
    private function resolvePayload(array $payload, array $fields, bool $translatable): array
    {
        foreach ($fields as $field) {
            if ($field instanceof Tabs) {
                foreach ($field->getTabs() as $tab) {
                    $payload = $this->resolvePayload($payload, $tab->getFields(), $translatable);
                }

                continue;
            }

            if ($field instanceof Fieldset) {
                $payload = $this->resolvePayload($payload, $field->getFields(), $translatable);

                continue;
            }

            if ($field instanceof Repeater) {
                $items = NestedData::get($payload, $field->getName(), []);

                if (! is_array($items)) {
                    continue;
                }

                foreach ($items as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $items[$index] = $this->resolveRepeaterItem($item, $field->getFields(), $translatable);
                }

                $payload = NestedData::set($payload, $field->getName(), $items);

                continue;
            }

            if (! $field instanceof PictureField || $field->isTranslatable() !== $translatable) {
                continue;
            }

            if (! NestedData::has($payload, $field->getName())) {
                continue;
            }

            $payload = NestedData::set(
                $payload,
                $field->getName(),
                $this->resolvePictureFieldValue(
                    NestedData::get($payload, $field->getName()),
                    $field->isMultiple(),
                ),
            );
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Field[]  $fields
     * @return array<string, mixed>
     */
    private function normalizeRepeaterItem(array $item, array $fields, bool $translatable): array
    {
        foreach ($fields as $field) {
            if ($field instanceof Tabs) {
                foreach ($field->getTabs() as $tab) {
                    $item = $this->normalizeRepeaterItem($item, $tab->getFields(), $translatable);
                }

                continue;
            }

            if ($field instanceof Fieldset) {
                $item = $this->normalizeRepeaterItem($item, $field->getFields(), $translatable);

                continue;
            }

            if ($field instanceof Repeater) {
                $children = $item[$field->getName()] ?? [];

                if (! is_array($children)) {
                    continue;
                }

                foreach ($children as $index => $child) {
                    if (! is_array($child)) {
                        continue;
                    }

                    $children[$index] = $this->normalizeRepeaterItem($child, $field->getFields(), $translatable);
                }

                $item[$field->getName()] = $children;

                continue;
            }

            if (! $field instanceof PictureField || $field->isTranslatable() !== $translatable) {
                continue;
            }

            if (! array_key_exists($field->getName(), $item)) {
                continue;
            }

            $item[$field->getName()] = $this->normalizePictureFieldValue($item[$field->getName()], $field->isMultiple());
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Field[]  $fields
     * @return array<string, mixed>
     */
    private function resolveRepeaterItem(array $item, array $fields, bool $translatable): array
    {
        foreach ($fields as $field) {
            if ($field instanceof Tabs) {
                foreach ($field->getTabs() as $tab) {
                    $item = $this->resolveRepeaterItem($item, $tab->getFields(), $translatable);
                }

                continue;
            }

            if ($field instanceof Fieldset) {
                $item = $this->resolveRepeaterItem($item, $field->getFields(), $translatable);

                continue;
            }

            if ($field instanceof Repeater) {
                $children = $item[$field->getName()] ?? [];

                if (! is_array($children)) {
                    continue;
                }

                foreach ($children as $index => $child) {
                    if (! is_array($child)) {
                        continue;
                    }

                    $children[$index] = $this->resolveRepeaterItem($child, $field->getFields(), $translatable);
                }

                $item[$field->getName()] = $children;

                continue;
            }

            if (! $field instanceof PictureField || $field->isTranslatable() !== $translatable) {
                continue;
            }

            if (! array_key_exists($field->getName(), $item)) {
                continue;
            }

            $item[$field->getName()] = $this->resolvePictureFieldValue($item[$field->getName()], $field->isMultiple());
        }

        return $item;
    }

    private function normalizePictureFieldValue(mixed $value, bool $multiple): mixed
    {
        if ($multiple) {
            if (! is_array($value)) {
                return [];
            }

            return array_values(array_filter(
                array_map(fn (mixed $item) => $this->normalizeMediaReference($item), $value),
                fn (mixed $item) => $item !== null,
            ));
        }

        return $this->normalizeMediaReference($value);
    }

    private function resolvePictureFieldValue(mixed $value, bool $multiple): mixed
    {
        if ($multiple) {
            if (! is_array($value)) {
                return [];
            }

            return array_values(array_filter(
                array_map(fn (mixed $item) => $this->resolveMediaReference($item), $value),
                fn (mixed $item) => $item !== null,
            ));
        }

        return $this->resolveMediaReference($value);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMediaReference(mixed $value): ?array
    {
        if ($value === null || ! is_array($value)) {
            return null;
        }

        $mediaItemId = $value['media_item_id'] ?? $value['id'] ?? null;

        if (! is_numeric($mediaItemId)) {
            return null;
        }

        return [
            'media_item_id' => (int) $mediaItemId,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveMediaReference(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) && array_key_exists('path', $value)) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $mediaItemId = $value['media_item_id'] ?? $value['id'] ?? null;

        if (! is_numeric($mediaItemId)) {
            return null;
        }

        $mediaItem = CmsMediaItem::query()
            ->with('translations')
            ->find((int) $mediaItemId);

        if ($mediaItem === null) {
            return null;
        }

        return [
            'id' => $mediaItem->id,
            'media_item_id' => $mediaItem->id,
            'category_id' => $mediaItem->category_id,
            'disk' => $mediaItem->disk,
            'path' => $mediaItem->path,
            'url' => $mediaItem->url(),
            'webp_url' => $mediaItem->webpUrl(),
            'filename' => $mediaItem->filename,
            'mime_type' => $mediaItem->mime_type,
            'size' => $mediaItem->size,
            'width' => $mediaItem->width,
            'height' => $mediaItem->height,
            'focal_x' => $mediaItem->focal_x,
            'focal_y' => $mediaItem->focal_y,
            'translations' => $mediaItem->translations
                ->mapWithKeys(fn ($translation) => [$translation->locale => [
                    'alt' => $translation->alt,
                    'title' => $translation->title,
                ]])
                ->all(),
            'created_at' => $mediaItem->created_at,
        ];
    }
}
