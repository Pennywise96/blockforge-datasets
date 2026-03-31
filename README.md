# Blockforge Datasets

`blockforge/datasets` adds structured content collections to Blockforge CMS.

The package now uses:

- dataset type `code` as the stable identifier
- optional schema-driven custom fields
- built-in visibility windows instead of the old `status` and built-in `date`

## Core Model

Dataset types remain database-backed records that editors can manage in the CMS module.

Each type has:

- `name`
- `code`
- `description`
- `sort_order`

Entries keep a small built-in editorial core:

- `title`
- `slug`
- `visibility_mode`
- `visibility_ranges`
- `categories`

Everything else should be defined through a dataset schema.

## Schema Registration

Dataset schemas are registered on the CMS `Page` config with `registerDatasetSchemas(array $schemas)`.

Accepted schema sources:

- a directory path, scanned for `*/config.php`
- a direct config file path
- an inline `DatasetSchema` instance

Example:

```php
<?php

use Blockforge\Cms\Config\Page;
use Blockforge\Cms\Fields\NumberInput;
use Blockforge\Cms\Fields\PictureField;
use Blockforge\Cms\Fields\TextInput;
use Blockforge\Datasets\Schemas\DatasetSchema;

return Page::make('main')
    ->domain(config('cms.domain', 'localhost'))
    ->registerDatasetSchemas([
        'fileadmin/datasets',
        'fileadmin/sites/main/datasets',
        DatasetSchema::make('room')->fields([
            TextInput::make('room_code')->label('Room Code')->required(),
            TextInput::make('subtitle')->label('Subtitle')->translatable(),
            NumberInput::make('size')->label('Size'),
            PictureField::make('image')->label('Image'),
        ]),
    ]);
```

Resolution rules:

- all page-registered sources are loaded in order
- later file sources override earlier file sources for the same code
- inline schemas override file-based schemas for the same code
- if no schema is registered for a type, the type still works with built-in fields only

## Schema Files

Typical file-based schemas live under `public/fileadmin/datasets/{code}/config.php`.

Example:

```php
<?php

use Blockforge\Cms\Fields\Checkbox;
use Blockforge\Cms\Fields\NumberInput;
use Blockforge\Cms\Fields\TextInput;
use Blockforge\Datasets\Schemas\DatasetSchema;

return DatasetSchema::make('room')->fields([
    TextInput::make('room_code')->label('Room Code')->required(),
    TextInput::make('subtitle')->label('Subtitle')->translatable(),
    NumberInput::make('size')->label('Size (m²)'),
    Checkbox::make('has_balcony')->label('Balcony'),
]);
```

The `make()` argument is the dataset type code, not a human label.

The display name belongs to the dataset type database record, not to the schema file.

## Field Storage

Schema-backed fields are split by translatability:

- translatable fields are stored in `bf_dataset_translations.data`
- non-translatable fields are stored in `bf_datasets.config`

Built-ins stay outside the schema:

- `title` in `bf_dataset_translations`
- `slug` in `bf_datasets`
- `visibility_mode` in `bf_datasets`
- visibility ranges in `bf_dataset_visibility_ranges`
- categories through the existing pivot tables

## Visibility Model

Datasets no longer use the built-in `status` and built-in `date` model.

Entries now support:

- `disabled`
- `always`
- `scheduled`

Scheduled entries can contain one or more date ranges. Overlapping or directly adjacent ranges are merged on save.

Frontend helpers return only currently visible entries by default.

The editor still shows all entries and exposes computed visibility labels and current visibility state.

If a project needs an editorial display date, define it as an explicit schema field.

## Frontend Usage

The CMS frontend helpers continue to expose dataset entries through:

- `<bf:dataset.context>`
- `<bf:dataset.items>`
- `<bf:dataset.categories>`
- `<bf:dataset.detail>`

Entry objects now expose:

- built-ins like `title`, `slug`, `visibility_mode`, `is_visible_now`
- schema-backed fields through magic access, like `$item->room_code`
- `url()`
- `categories()`
- `hasCategory()`
- `get()`

`date()` still exists as a compatibility helper for legacy templates and falls back to migrated legacy values when present.

## Editor Architecture

The dataset editor module is schema-driven:

- built-in entry fields are rendered separately
- custom schema fields are rendered from serialized schema metadata
- visibility scheduling is edited through the built-in visibility panel

The package owns its editor bundle and consumes CMS editor internals only through the public CMS `editor-sdk`.

## Deployment Notes

Package editor assets are built in this package and must be published during deploy.

A deploy must ensure:

```bash
php artisan migrate --force
php artisan blockforge:update
```

If package editor assets changed, the deploy also needs the package build output available before `blockforge:update` republishes assets.

For local builds, Vite 7 requires Node 20.19+ or 22.12+.
