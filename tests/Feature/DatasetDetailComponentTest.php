<?php

use Blockforge\Cms\Elements\MediaItemObject;
use Blockforge\Cms\Models\CmsMediaItem;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Models\CmsSiteLocale;
use Blockforge\Datasets\Elements\DatasetObject;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\ViewHelpers\DatasetDetailViewHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function getDetailTestLocale(): CmsSiteLocale
{
    $site = CmsSite::query()->firstOrCreate(
        ['handle' => 'detail-test'],
        ['name' => 'Detail Test', 'domain' => 'detail.test', 'active' => true],
    );

    return CmsSiteLocale::query()->firstOrCreate(
        ['site_id' => $site->id, 'locale' => 'en'],
        ['label' => 'English', 'is_default' => true, 'is_prefixed' => false, 'translation_mode' => 'fallback', 'sort_order' => 0],
    );
}

function makeDetailType(string $code = 'blog'): CmsDatasetType
{
    return CmsDatasetType::query()->firstOrCreate(
        ['code' => $code],
        ['name' => ucfirst($code)],
    );
}

function makeDetailEntry(CmsDatasetType $type, string $slug, string $title = 'Test Entry'): CmsDataset
{
    $dataset = CmsDataset::query()->create([
        'type_id' => $type->id,
        'slug' => $slug,
        'visibility_mode' => 'always',
    ]);

    $dataset->translations()->create([
        'locale' => 'en',
        'title' => $title,
        'data' => [
            'excerpt' => 'An excerpt.',
        ],
    ]);

    return $dataset;
}

/** @param array<string, mixed> $args */
function executeDetailViewHelper(array $args): array
{
    $captured = [];

    app(DatasetDetailViewHelper::class)->execute(
        $args,
        function (array $vars) use (&$captured): string {
            $captured = $vars;

            return '';
        }
    );

    return $captured;
}

test('returns empty string when no slug is bound and no prop is given', function (): void {
    makeDetailType('blog');

    $captured = executeDetailViewHelper(['type' => 'blog', 'as' => 'post']);

    expect($captured)->toBeEmpty();
});

test('fetches the correct entry when an explicit slug prop is given', function (): void {
    $type = makeDetailType('blog');
    makeDetailEntry($type, 'my-post', 'My Post');

    app()->instance('cms.locale', 'en');

    $captured = executeDetailViewHelper(['type' => 'blog', 'slug' => 'my-post', 'as' => 'post']);

    expect($captured['post'])
        ->toBeInstanceOf(DatasetObject::class)
        ->and($captured['post']->title)->toBe('My Post')
        ->and($captured['post']->slug)->toBe('my-post');
});

test('fetches the correct entry when slug is bound via cms.dataset_slug', function (): void {
    $type = makeDetailType('blog');
    makeDetailEntry($type, 'bound-post', 'Bound Post');

    app()->instance('cms.locale', 'en');
    app()->instance('cms.dataset_slug', 'bound-post');

    $captured = executeDetailViewHelper(['type' => 'blog', 'as' => 'post']);

    expect($captured['post'])
        ->toBeInstanceOf(DatasetObject::class)
        ->and($captured['post']->title)->toBe('Bound Post');
});

test('resolves the detail entry from bound cms.dataset_type context', function (): void {
    $type = makeDetailType('blog');
    makeDetailEntry($type, 'bound-post', 'Bound Post');

    app()->instance('cms.locale', 'en');
    app()->instance('cms.dataset_slug', 'bound-post');
    app()->instance('cms.dataset_type', $type);

    $captured = executeDetailViewHelper(['as' => 'post']);

    expect($captured['post'])
        ->toBeInstanceOf(DatasetObject::class)
        ->and($captured['post']->title)->toBe('Bound Post');
});

test('explicit slug prop takes priority over cms.dataset_slug', function (): void {
    $type = makeDetailType('blog');
    makeDetailEntry($type, 'explicit-post', 'Explicit Post');
    makeDetailEntry($type, 'bound-post', 'Bound Post');

    app()->instance('cms.locale', 'en');
    app()->instance('cms.dataset_slug', 'bound-post');

    $captured = executeDetailViewHelper(['type' => 'blog', 'slug' => 'explicit-post', 'as' => 'post']);

    expect($captured['post']->title)->toBe('Explicit Post');
});

test('explicit type prop takes priority over bound cms.dataset_type', function (): void {
    $blogType = makeDetailType('blog');
    $newsType = makeDetailType('news');
    makeDetailEntry($blogType, 'my-post', 'Blog Post');
    makeDetailEntry($newsType, 'my-post', 'News Post');

    app()->instance('cms.locale', 'en');
    app()->instance('cms.dataset_slug', 'my-post');
    app()->instance('cms.dataset_type', $newsType);

    $captured = executeDetailViewHelper(['type' => 'blog', 'as' => 'post']);

    expect($captured['post']->title)->toBe('Blog Post')
        ->and($captured['post']->type)->toBe('blog');
});

test('aborts 404 when slug is set but no matching visible entry exists', function (): void {
    makeDetailType('blog');

    app()->instance('cms.dataset_slug', 'nonexistent-slug');

    expect(fn () => executeDetailViewHelper(['type' => 'blog', 'as' => 'post']))
        ->toThrow(HttpException::class);
});

test('returns empty string when type does not exist', function (): void {
    $captured = executeDetailViewHelper(['type' => 'nonexistent-type', 'slug' => 'some-slug', 'as' => 'post']);

    expect($captured)->toBeEmpty();
});

test('injected variable uses the as parameter name', function (): void {
    $type = makeDetailType('news');
    makeDetailEntry($type, 'article-1', 'Article 1');

    app()->instance('cms.locale', 'en');

    $captured = executeDetailViewHelper(['type' => 'news', 'slug' => 'article-1', 'as' => 'article']);

    expect($captured)->toHaveKeys(['item', 'article'])
        ->and($captured['article'])->toBe($captured['item']);
});

test('resolves stored media references to media item objects', function (): void {
    $type = makeDetailType('blog');
    $entry = makeDetailEntry($type, 'with-image', 'With Image');
    $mediaItem = CmsMediaItem::query()->create([
        'category_id' => null,
        'disk' => 'public',
        'path' => 'media/blog/reference.jpg',
        'webp_path' => null,
        'filename' => 'reference.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'width' => 1200,
        'height' => 800,
        'focal_x' => 0.4,
        'focal_y' => 0.6,
    ]);

    $mediaItem->translations()->create([
        'site_locale_id' => getDetailTestLocale()->id,
        'locale' => 'en',
        'alt' => 'Reference image',
        'title' => 'Reference',
    ]);

    $entry->translations()->where('locale', 'en')->update([
        'data' => [
            'image' => [
                'media_item_id' => $mediaItem->id,
            ],
        ],
    ]);

    app()->instance('cms.locale', 'en');

    $captured = executeDetailViewHelper(['type' => 'blog', 'slug' => 'with-image', 'as' => 'post']);

    expect($captured['post']->image)->toBeInstanceOf(MediaItemObject::class)
        ->and($captured['post']->image->media_item_id)->toBe($mediaItem->id)
        ->and($captured['post']->image->filename)->toBe('reference.jpg')
        ->and($captured['post']->image->alt)->toBe('Reference image')
        ->and($captured['post']->image->objectPosition())->toBe('40% 60%');
});
