<?php

use Blockforge\Cms\Config\Page;
use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Models\CmsSiteLocale;
use Blockforge\Datasets\Elements\DatasetObject;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetDetailPage;
use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\ViewHelpers\DatasetItemsViewHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeItemsTestSite(): CmsSite
{
    return CmsSite::query()->create([
        'handle' => 'items-test-'.uniqid(),
        'name' => 'Items Test',
        'domain' => 'items.test',
        'active' => true,
    ]);
}

function makeItemsLocale(CmsSite $site): CmsSiteLocale
{
    return CmsSiteLocale::query()->create([
        'site_id' => $site->id,
        'locale' => 'en',
        'label' => 'English',
        'is_default' => true,
        'is_prefixed' => false,
        'translation_mode' => 'fallback',
        'sort_order' => 0,
    ]);
}

function makeItemsType(string $slug = 'blog'): CmsDatasetType
{
    return CmsDatasetType::query()->create([
        'name' => ucfirst($slug),
        'slug' => $slug,
    ]);
}

function makeItemsPage(CmsSite $site, array $attributes = []): CmsPage
{
    return CmsPage::query()->create(array_merge([
        'site_id' => $site->id,
        'slug' => 'test-page',
        'sort_order' => 0,
        'status' => 'published',
    ], $attributes));
}

function makeItemsEntry(CmsDatasetType $type, string $slug, string $title): CmsDataset
{
    $dataset = CmsDataset::query()->create([
        'type_id' => $type->id,
        'slug' => $slug,
        'status' => 'published',
    ]);

    $dataset->translations()->create([
        'locale' => 'en',
        'title' => $title,
        'excerpt' => 'Excerpt for '.$title,
    ]);

    return $dataset;
}

function makeItemsCategory(CmsDatasetType $type, string $slug, string $name): CmsDatasetCategory
{
    return CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'name' => $name,
        'slug' => $slug,
    ]);
}

/** @return array<int, array<string, mixed>> */
function executeItemsViewHelper(array $args): array
{
    $captured = [];

    app(DatasetItemsViewHelper::class)->execute(
        $args,
        function (array $vars) use (&$captured): string {
            $captured[] = $vars;

            return '';
        }
    );

    return $captured;
}

function bindItemsRuntimeContext(CmsSite $site, CmsSiteLocale $locale, string $path = '/', array $query = []): void
{
    app()->instance(Page::class, Page::make($site->handle)->domain('items.test'));
    app()->instance(CmsSite::class, $site);
    app()->instance('cms.locale', $locale->locale);
    app()->instance('cms.locale_id', $locale->id);
    app()->instance('cms.site_locale', $locale);
    app()->instance('cms.site_locales', new Collection([$locale]));
    app()->instance('request', Request::create($path, 'GET', $query));
}

test('generates canonical detail URLs for dataset entries', function (): void {
    $site = makeItemsTestSite();
    $locale = makeItemsLocale($site);
    $type = makeItemsType('blog');
    makeItemsEntry($type, 'hello-world', 'Hello World');

    $archivePage = makeItemsPage($site, ['slug' => 'blog']);
    $detailPage = makeItemsPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'nav_hidden' => true,
    ]);

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $detailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    bindItemsRuntimeContext($site, $locale);

    $captured = executeItemsViewHelper(['type' => 'blog', 'as' => 'post']);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['post'])->toBeInstanceOf(DatasetObject::class)
        ->and($captured[0]['post']->title)->toBe('Hello World')
        ->and($captured[0]['post']->url())->toBe('/blog/hello-world');
});

test('returns no detail url when no canonical mapping exists', function (): void {
    $site = makeItemsTestSite();
    $locale = makeItemsLocale($site);
    $type = makeItemsType('blog');
    makeItemsEntry($type, 'legacy-post', 'Legacy Post');

    $listPage = makeItemsPage($site, ['slug' => 'blog']);

    bindItemsRuntimeContext($site, $locale);
    app()->instance(CmsPage::class, $listPage);

    $captured = executeItemsViewHelper(['type' => 'blog', 'as' => 'post']);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['post']->url())->toBeNull();
});

test('applies archive category filters from bound route context', function (): void {
    $site = makeItemsTestSite();
    $locale = makeItemsLocale($site);
    $type = makeItemsType('blog');
    $news = makeItemsCategory($type, 'news', 'News');
    $updates = makeItemsCategory($type, 'updates', 'Updates');
    $first = makeItemsEntry($type, 'first-post', 'First Post');
    $second = makeItemsEntry($type, 'second-post', 'Second Post');
    $first->categories()->attach($news->id);
    $second->categories()->attach($updates->id);

    bindItemsRuntimeContext($site, $locale, '/blog/category/news');
    app()->instance('cms.dataset_filters', ['category' => 'news']);

    $captured = executeItemsViewHelper(['type' => 'blog', 'as' => 'post']);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['post']->slug)->toBe('first-post');
});

test('applies archive pagination filters from bound route context', function (): void {
    $site = makeItemsTestSite();
    $locale = makeItemsLocale($site);
    $type = makeItemsType('blog');
    makeItemsEntry($type, 'post-1', 'Post 1');
    makeItemsEntry($type, 'post-2', 'Post 2');
    makeItemsEntry($type, 'post-3', 'Post 3');

    bindItemsRuntimeContext($site, $locale, '/blog/page/2');
    app()->instance('cms.dataset_filters', ['page' => 2]);

    $captured = executeItemsViewHelper([
        'type' => 'blog',
        'as' => 'post',
        'limit' => 2,
        'orderBy' => 'created_at',
        'direction' => 'asc',
    ]);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['post']->slug)->toBe('post-3');
});

test('applies search filters from the request query string', function (): void {
    $site = makeItemsTestSite();
    $locale = makeItemsLocale($site);
    $type = makeItemsType('blog');
    makeItemsEntry($type, 'laravel-post', 'Laravel Deep Dive');
    makeItemsEntry($type, 'symfony-post', 'Symfony Update');

    bindItemsRuntimeContext($site, $locale, '/blog', ['q' => 'laravel']);

    $captured = executeItemsViewHelper(['type' => 'blog', 'as' => 'post']);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['post']->slug)->toBe('laravel-post');
});
