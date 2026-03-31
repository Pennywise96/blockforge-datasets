<?php

namespace Blockforge\Datasets\Elements;

use Blockforge\Cms\Elements\MediaItemObject;
use Blockforge\Cms\Models\CmsMediaItem;
use Illuminate\Support\Carbon;

/**
 * Lightweight value object wrapping a CmsDataset entry with its resolved translation.
 *
 * Fixed fields: id, type, slug, title, visibility_mode, is_visible_now
 * Extra configurable fields are accessible via get() or magic property access.
 */
class DatasetObject
{
    /**
     * @param  array<string, mixed>  $fields  Fixed + translatable fields
     * @param  array<string, mixed>  $fieldValues  Non-translatable custom field values
     * @param  array<string, mixed>  $translatedFieldValues  Translatable custom field values
     * @param  array<array<string, mixed>>  $categories  Category name/slug pairs
     * @param  string|null  $detailBase  Base path for generating the entry URL (e.g. '/blog')
     */
    public function __construct(
        private array $fields = [],
        private array $fieldValues = [],
        private array $translatedFieldValues = [],
        private array $categories = [],
        private ?string $detailBase = null,
    ) {
        $this->fields = array_merge([
            'id' => null,
            'type' => '',
            'slug' => '',
            'title' => '',
            'visibility_mode' => 'disabled',
            'is_visible_now' => false,
        ], $fields);
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->fields)) {
            return $this->fields[$name];
        }

        if (array_key_exists($name, $this->translatedFieldValues)) {
            return $this->resolveExtraValue($this->translatedFieldValues[$name]);
        }

        return array_key_exists($name, $this->fieldValues)
            ? $this->resolveExtraValue($this->fieldValues[$name])
            : null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->fields)
            || array_key_exists($name, $this->translatedFieldValues)
            || array_key_exists($name, $this->fieldValues);
    }

    /**
     * Get an extra configurable field value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->translatedFieldValues)) {
            return $this->resolveExtraValue($this->translatedFieldValues[$key]);
        }

        if (array_key_exists($key, $this->fieldValues)) {
            return $this->resolveExtraValue($this->fieldValues[$key]);
        }

        return $default;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->categories;
    }

    public function hasCategory(string $slug): bool
    {
        foreach ($this->categories as $category) {
            if (($category['slug'] ?? null) === $slug) {
                return true;
            }
        }

        return false;
    }

    public function date(): ?Carbon
    {
        $value = $this->fields['date']
            ?? $this->translatedFieldValues['date']
            ?? $this->fieldValues['date']
            ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * Returns the full URL to this entry's detail page, or null if no detail base is configured.
     */
    public function url(): ?string
    {
        if ($this->detailBase === null) {
            return null;
        }

        $slug = $this->fields['slug'] ?? '';

        return $slug !== '' ? rtrim($this->detailBase, '/').'/'.$slug : null;
    }

    private function resolveExtraValue(mixed $value): mixed
    {
        $mediaPayload = $this->resolveMediaPayload($value);

        if ($mediaPayload === null) {
            return $value;
        }

        $locale = app()->bound('cms.locale') ? app('cms.locale') : app()->getLocale();
        $translation = is_array($mediaPayload['translations'][$locale] ?? null)
            ? $mediaPayload['translations'][$locale]
            : [];

        return new MediaItemObject([
            'id' => $mediaPayload['id'] ?? null,
            'media_item_id' => $mediaPayload['media_item_id'] ?? $mediaPayload['id'] ?? null,
            'disk' => $mediaPayload['disk'] ?? 'public',
            'path' => $mediaPayload['path'] ?? null,
            'filename' => $mediaPayload['filename'] ?? null,
            'mime_type' => $mediaPayload['mime_type'] ?? null,
            'size' => $mediaPayload['size'] ?? null,
            'width' => $mediaPayload['width'] ?? null,
            'height' => $mediaPayload['height'] ?? null,
            'url' => $mediaPayload['url'] ?? null,
            'webp_url' => $mediaPayload['webp_url'] ?? null,
            'focal_x' => $mediaPayload['focal_x'] ?? 0.5,
            'focal_y' => $mediaPayload['focal_y'] ?? 0.5,
            'alt' => $translation['alt'] ?? null,
            'title' => $translation['title'] ?? null,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function resolveMediaPayload(mixed $value): ?array
    {
        if (! $this->looksLikeMediaItem($value)) {
            return null;
        }

        if (array_key_exists('path', $value)) {
            return $value;
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
        ];
    }

    private function looksLikeMediaItem(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        return array_key_exists('media_item_id', $value)
            || (array_key_exists('id', $value) && array_key_exists('path', $value));
    }
}
