<?php

use App\Models\User;
use Blockforge\Cms\Http\Middleware\ResolveCmsSite;
use Blockforge\Cms\Models\CmsMediaItem;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Models\CmsSiteLocale;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetTranslation;
use Blockforge\Datasets\Models\CmsDatasetType;
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
        ['slug' => $slug],
        ['name' => ucfirst($slug)],
    );
}

function makeDataset(CmsDatasetType $type, array $attrs = []): CmsDataset
{
    return CmsDataset::query()->create(array_merge([
        'type_id' => $type->id,
        'slug' => 'test-entry',
        'status' => 'published',
    ], $attrs));
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

test('can filter datasets by status', function (): void {
    $type = makeDatasetType();
    makeDataset($type, ['slug' => 'published-post', 'status' => 'published']);
    makeDataset($type, ['slug' => 'draft-post', 'status' => 'draft']);

    $this->getJson('/api/cms/datasets?type=blog&status=published')
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
        'status' => 'draft',
        'date' => '2026-03-01',
    ])->assertCreated()
        ->assertJsonPath('slug', 'my-post')
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('date', '2026-03-01');
});

test('create dataset requires type_id and slug', function (): void {
    $this->postJson('/api/cms/datasets', [])
        ->assertUnprocessable();
});

test('create dataset rejects invalid status', function (): void {
    $type = makeDatasetType();

    $this->postJson('/api/cms/datasets', [
        'type_id' => $type->id,
        'slug' => 'post',
        'status' => 'archived',
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
        'status' => 'published',
        'date' => '2026-06-15',
    ])->assertOk()
        ->assertJsonPath('status', 'published')
        ->assertJsonPath('date', '2026-06-15');
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
    $mediaItem = makeDatasetMediaItem();

    $this->putJson("/api/cms/datasets/{$dataset->id}/translations/en", [
        'title' => 'My Blog Post',
        'excerpt' => 'Short excerpt here.',
        'content' => 'Full content body.',
        'data' => [
            'image' => [
                'id' => $mediaItem->id,
                'filename' => 'ignored-by-normalizer.jpg',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('title', 'My Blog Post')
        ->assertJsonPath('locale', 'en')
        ->assertJsonPath('data.image.id', $mediaItem->id)
        ->assertJsonPath('data.image.media_item_id', $mediaItem->id)
        ->assertJsonPath('data.image.filename', 'cover.jpg')
        ->assertJsonPath('data.image.url', $mediaItem->url())
        ->assertJsonPath('data.image.webp_url', null);

    $translation = CmsDatasetTranslation::query()
        ->where('dataset_id', $dataset->id)
        ->where('locale', 'en')
        ->first();

    expect($translation)->not->toBeNull()
        ->and($translation?->title)->toBe('My Blog Post')
        ->and($translation?->data)->toMatchArray([
            'image' => [
                'media_item_id' => $mediaItem->id,
            ],
        ]);
});

test('can update existing translation', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);
    $dataset->translations()->create(['locale' => 'en', 'title' => 'Old Title']);
    $mediaItem = makeDatasetMediaItem('replacement.jpg');

    $this->putJson("/api/cms/datasets/{$dataset->id}/translations/en", [
        'title' => 'New Title',
        'data' => [
            'image' => [
                'id' => $mediaItem->id,
                'filename' => 'replacement.jpg',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('title', 'New Title')
        ->assertJsonPath('data.image.id', $mediaItem->id)
        ->assertJsonPath('data.image.media_item_id', $mediaItem->id)
        ->assertJsonPath('data.image.path', 'media/replacement.jpg')
        ->assertJsonPath('data.image.url', $mediaItem->url())
        ->assertJsonPath('data.image.webp_url', null);

    $translation = CmsDatasetTranslation::query()
        ->where('dataset_id', $dataset->id)
        ->where('locale', 'en')
        ->first();

    expect(
        CmsDatasetTranslation::query()->where('dataset_id', $dataset->id)->count()
    )->toBe(1)
        ->and($translation?->data)->toMatchArray([
            'image' => [
                'media_item_id' => $mediaItem->id,
            ],
        ]);
});

test('translation update requires title', function (): void {
    $type = makeDatasetType();
    $dataset = makeDataset($type);

    $this->putJson("/api/cms/datasets/{$dataset->id}/translations/en", [])
        ->assertUnprocessable();
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
