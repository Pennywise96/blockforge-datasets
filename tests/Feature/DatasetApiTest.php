<?php

use App\Models\User;
use Blockforge\Cms\Config\Page;
use Blockforge\Cms\Fields\PictureField;
use Blockforge\Cms\Fields\TextInput;
use Blockforge\Cms\Http\Middleware\ResolveCmsSite;
use Blockforge\Cms\Models\CmsMediaItem;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Models\CmsSiteLocale;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetTranslation;
use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\Schemas\DatasetSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ResolveCmsSite::class);
    $this->actingAs(User::factory()->create());
});

function getDatasetTestLocale(): CmsSiteLocale
{
    $site = CmsSite::query()->firstOrCreate(
        ['handle' => 'dataset-test'],
        ['name' => 'Dataset Test', 'domain' => 'dataset.test', 'active' => true],
    );

    return CmsSiteLocale::query()->firstOrCreate(
        ['site_id' => $site->id, 'locale' => 'en'],
        ['label' => 'English', 'is_default' => true, 'is_prefixed' => false, 'translation_mode' => 'fallback', 'sort_order' => 0],
    );
}

function makeDatasetType(string $slug = 'blog'): CmsDatasetType
{
    return CmsDatasetType::query()->firstOrCreate(
        ['code' => $slug],
        ['name' => ucfirst($slug)],
    );
}

function makeDataset(CmsDatasetType $type, array $attrs = []): CmsDataset
{
    return CmsDataset::query()->create(array_merge([
        'type_id' => $type->id,
        'slug' => 'test-entry',
        'visibility_mode' => 'always',
    ], $attrs));
}

function bindDatasetSchema(DatasetSchema ...$schemas): void
{
    app()->instance(
        Page::class,
        Page::make('dataset-test')->registerDatasetSchemas($schemas),
    );
}

function makeDatasetMediaItem(string $filename = 'cover.jpg'): CmsMediaItem
{
    $mediaItem = CmsMediaItem::query()->create([
        'category_id' => null,
        'disk' => 'public',
        'path' => 'media/'.$filename,
        'webp_path' => null,
        'filename' => $filename,
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'width' => 1600,
        'height' => 900,
        'focal_x' => 0.25,
        'focal_y' => 0.75,
    ]);

    $locale = getDatasetTestLocale();

    $mediaItem->translations()->create([
        'site_locale_id' => $locale->id,
        'locale' => 'en',
        'alt' => 'Cover image',
        'title' => 'Cover',
    ]);

    return $mediaItem;
}

test('can list datasets', function (): void {
    $type = makeDatasetType();
    makeDataset($type, ['slug' => 'post-1']);
    makeDataset($type, ['slug' => 'post-2']);

    $this->getJson('/api/cms/datasets?type=blog')
        ->assertOk()
        ->assertJsonPath('total', 2);
});

test('can filter datasets by visibility', function (): void {
    $type = makeDatasetType();
    makeDataset($type, ['slug' => 'always-post', 'visibility_mode' => 'always']);
    makeDataset($type, ['slug' => 'disabled-post', 'visibility_mode' => 'disabled']);

    $this->getJson('/api/cms/datasets?type=blog&visibility=always')
        ->assertOk()
        ->assertJsonPath('total', 1);
});

test('can filter datasets by category', function (): void {
    $type = makeDatasetType();
    $cat = CmsDatasetCategory::query()->create(['type_id' => $type->id, 'name' => 'Tech', 'slug' => 'tech']);
    $d1 = makeDataset($type, ['slug' => 'tech-post']);
    $d2 = makeDataset($type, ['slug' => 'other-post']);
    $d1->categories()->attach($cat->id);

    $this->getJson('/api/cms/datasets?type=blog&category=tech')
        ->assertOk()
        ->assertJsonPath('total', 1);
});

test('can create a dataset entry', function (): void {
    $type = makeDatasetType();

    $this->postJson('/api/cms/datasets', [
        'type_id' => $type->id,
        'slug' => 'my-post',
        'visibility_mode' => 'scheduled',
        'visibility_ranges' => [
            [
                'starts_at' => '2026-03-01T10:00:00Z',
                'ends_at' => '2026-03-10T10:00:00Z',
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('slug', 'my-post')
        ->assertJsonPath('visibility_mode', 'scheduled')
        ->assertJsonPath('visibility_ranges.0.starts_at', '2026-03-01T10:00:00.000000Z')
        ->assertJsonPath('visibility_ranges.0.ends_at', '2026-03-10T10:00:00.000000Z');
});

test('create dataset requires type_id and slug', function (): void {
    $this->postJson('/api/cms/datasets', [])
        ->assertUnprocessable();
});

test('create dataset rejects invalid visibility mode', function (): void {
    $type = makeDatasetType();

    $this->postJson('/api/cms/datasets', [
        'type_id' => $type->id,
        'slug' => 'post',
        'visibility_mode' => 'archived',
    ])->assertUnprocessable();
});

test('can show a dataset entry', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);

    $this->getJson("/api/cms/datasets/{$dataset->id}")
        ->assertOk()
        ->assertJsonPath('id', $dataset->id)
        ->assertJsonPath('slug', 'test-entry');
});

test('can update a dataset entry', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);

    $this->putJson("/api/cms/datasets/{$dataset->id}", [
        'visibility_mode' => 'scheduled',
        'visibility_ranges' => [
            [
                'starts_at' => '2026-06-15T08:00:00Z',
                'ends_at' => '2026-06-20T20:00:00Z',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('visibility_mode', 'scheduled')
        ->assertJsonPath('visibility_ranges.0.starts_at', '2026-06-15T08:00:00.000000Z')
        ->assertJsonPath('visibility_ranges.0.ends_at', '2026-06-20T20:00:00.000000Z');
});

test('can delete a dataset entry', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);

    $this->deleteJson("/api/cms/datasets/{$dataset->id}")
        ->assertNoContent();

    expect(CmsDataset::query()->find($dataset->id))->toBeNull();
});

test('can create a translation for a dataset entry', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);
    bindDatasetSchema(
        DatasetSchema::make('blog')->fields([
            TextInput::make('subtitle')->label('Subtitle')->translatable(),
        ]),
    );

    $this->putJson("/api/cms/datasets/{$dataset->id}/translations/en", [
        'title' => 'My Blog Post',
        'data' => [
            'subtitle' => 'Short teaser',
        ],
    ])->assertOk()
        ->assertJsonPath('title', 'My Blog Post')
        ->assertJsonPath('locale', 'en')
        ->assertJsonPath('data.subtitle', 'Short teaser');

    $translation = CmsDatasetTranslation::query()
        ->where('dataset_id', $dataset->id)
        ->where('locale', 'en')
        ->first();

    expect($translation)->not->toBeNull()
        ->and($translation?->title)->toBe('My Blog Post')
        ->and($translation?->data)->toMatchArray([
            'subtitle' => 'Short teaser',
        ]);
});

test('can update existing translation', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);
    $dataset->translations()->create(['locale' => 'en', 'title' => 'Old Title']);
    bindDatasetSchema(
        DatasetSchema::make('blog')->fields([
            TextInput::make('subtitle')->label('Subtitle')->translatable(),
        ]),
    );

    $this->putJson("/api/cms/datasets/{$dataset->id}/translations/en", [
        'title' => 'New Title',
        'data' => [
            'subtitle' => 'Updated subtitle',
        ],
    ])->assertOk()
        ->assertJsonPath('title', 'New Title')
        ->assertJsonPath('data.subtitle', 'Updated subtitle');

    $translation = CmsDatasetTranslation::query()
        ->where('dataset_id', $dataset->id)
        ->where('locale', 'en')
        ->first();

    expect(
        CmsDatasetTranslation::query()->where('dataset_id', $dataset->id)->count()
    )->toBe(1)
        ->and($translation?->data)->toMatchArray([
            'subtitle' => 'Updated subtitle',
        ]);
});

test('normalizes and resolves schema-backed picture fields on dataset config', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);
    $mediaItem = makeDatasetMediaItem();
    bindDatasetSchema(
        DatasetSchema::make('blog')->fields([
            PictureField::make('image')->label('Image'),
        ]),
    );

    $this->putJson("/api/cms/datasets/{$dataset->id}", [
        'slug' => 'test-entry',
        'visibility_mode' => 'always',
        'config' => [
            'image' => [
                'id' => $mediaItem->id,
                'filename' => 'ignored-by-normalizer.jpg',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('config.image.id', $mediaItem->id)
        ->assertJsonPath('config.image.media_item_id', $mediaItem->id)
        ->assertJsonPath('config.image.filename', 'cover.jpg')
        ->assertJsonPath('config.image.url', $mediaItem->url())
        ->assertJsonPath('config.image.webp_url', null);
});

test('translation update requires title', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);

    $this->putJson("/api/cms/datasets/{$dataset->id}/translations/en", [])
        ->assertUnprocessable();
});

test('persists schema-backed translatable and non-translatable fields', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);
    bindDatasetSchema(
        DatasetSchema::make('blog')->fields([
            TextInput::make('room_code')->label('Room Code')->required(),
            TextInput::make('subtitle')->label('Subtitle')->translatable(),
        ]),
    );

    $this->putJson("/api/cms/datasets/{$dataset->id}", [
        'slug' => 'room-a',
        'visibility_mode' => 'always',
        'config' => [
            'room_code' => 'A-01',
        ],
    ])->assertOk()
        ->assertJsonPath('config.room_code', 'A-01');

    $this->putJson("/api/cms/datasets/{$dataset->id}/translations/en", [
        'title' => 'Room A',
        'data' => [
            'subtitle' => 'Lake view',
        ],
    ])->assertOk()
        ->assertJsonPath('data.subtitle', 'Lake view');

    expect($dataset->fresh()->config)->toMatchArray([
        'room_code' => 'A-01',
    ]);

    expect($dataset->fresh()->translations()->where('locale', 'en')->first()?->data)->toMatchArray([
        'subtitle' => 'Lake view',
    ]);
});

test('can sync categories for a dataset entry', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);
    $cat1 = CmsDatasetCategory::query()->create(['type_id' => $type->id, 'name' => 'Tech', 'slug' => 'tech']);
    $cat2 = CmsDatasetCategory::query()->create(['type_id' => $type->id, 'name' => 'News', 'slug' => 'news']);

    $this->putJson("/api/cms/datasets/{$dataset->id}/categories", [
        'category_ids' => [$cat1->id, $cat2->id],
    ])->assertOk();

    expect($dataset->fresh()->categories)->toHaveCount(2);
});

test('cannot sync categories from another dataset type', function (): void {
    $type = makeDatasetType();
    $otherType = makeDatasetType('news');
    $dataset = makeDataset($type);
    $foreignCategory = CmsDatasetCategory::query()->create([
        'type_id' => $otherType->id,
        'name' => 'News',
        'slug' => 'news',
    ]);

    $this->putJson("/api/cms/datasets/{$dataset->id}/categories", [
        'category_ids' => [$foreignCategory->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['category_ids']);
});

test('can remove all categories via sync', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);
    $cat = CmsDatasetCategory::query()->create(['type_id' => $type->id, 'name' => 'Tech', 'slug' => 'tech']);
    $dataset->categories()->attach($cat->id);

    $this->putJson("/api/cms/datasets/{$dataset->id}/categories", [
        'category_ids' => [],
    ])->assertOk();

    expect($dataset->fresh()->categories)->toHaveCount(0);
});

test('deleting a type cascades to its datasets', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);
    $dataset->translations()->create(['locale' => 'en', 'title' => 'Post']);

    $type->delete();

    expect(CmsDataset::query()->find($dataset->id))->toBeNull()
        ->and(CmsDatasetTranslation::query()->where('dataset_id', $dataset->id)->count())->toBe(0);
});
