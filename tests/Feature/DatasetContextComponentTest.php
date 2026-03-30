<?php

use Blockforge\Cms\Config\Page;
use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Models\CmsSiteLocale;
use Blockforge\Datasets\Elements\DatasetCategoryObject;
use Blockforge\Datasets\Elements\DatasetContextObject;
use Blockforge\Datasets\Elements\DatasetObject;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetDetailPage;
use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\ViewHelpers\DatasetCategoriesViewHelper;
use Blockforge\Datasets\ViewHelpers\DatasetContextViewHelper;
use Blockforge\Datasets\ViewHelpers\DatasetItemsViewHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeContextTestSite(): CmsSite
{
    return CmsSite::query()->create([
        'handle' => 'context-test-'.uniqid(),
        'name' => 'Context Test',
        'domain' => 'context.test',
        'active' => true,
    ]);
}

function makeContextLocale(CmsSite $site): CmsSiteLocale
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

function makeContextType(string $slug = 'blog'): CmsDatasetType
{
    return CmsDatasetType::query()->create([
        'name' => ucfirst($slug),
        'slug' => $slug,
    ]);
}

function makeContextPage(CmsSite $site, array $attributes = []): CmsPage
{
    return CmsPage::query()->create(array_merge([
        'site_id' => $site->id,
        'slug' => 'test-page',
        'sort_order' => 0,
        'status' => 'published',
    ], $attributes));
}

function makeContextEntry(CmsDatasetType $type, string $slug, string $title): CmsDataset
{
    $dataset = CmsDataset::query()->create([
        'type_id' => $type->id,
        'slug' => $slug,
        'status' => 'published',
    ]);

    $dataset->translations()->create([
        'locale' => 'en',
        'title' => $title,
    ]);

    return $dataset;
}

function makeContextCategory(CmsDatasetType $type, string $slug, string $name): CmsDatasetCategory
{
    return CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'name' => $name,
        'slug' => $slug,
    ]);
}

function bindContextRuntime(CmsSite $site, CmsSiteLocale $locale, CmsPage $page, string $path = '/', array $query = []): void
{
    app()->instance(Page::class, Page::make($site->handle)->domain('context.test'));
    app()->instance(CmsSite::class, $site);
    app()->instance(CmsPage::class, $page);
    app()->instance('cms.locale', $locale->locale);
    app()->instance('cms.locale_id', $locale->id);
    app()->instance('cms.site_locale', $locale);
    app()->instance('cms.site_locales', new Collection([$locale]));
    app()->instance('request', Request::create($path, 'GET', $query));
}

/** @return array<string, mixed> */
function executeContextViewHelper(array $args, ?callable $children = null): array
{
    $captured = [];

    app(DatasetContextViewHelper::class)->execute(
        $args,
        function (array $vars) use (&$captured, $children): string {
            $captured = $vars;

            if ($children !== null) {
                $children($vars);
            }

            return '';
        }
    );

    return $captured;
}

test('resolves a dataset context object from the current archive page without an explicit type', function (): void {
    $site = makeContextTestSite();
    $locale = makeContextLocale($site);
    $type = makeContextType('blog');
    $archivePage = makeContextPage($site, ['slug' => 'blog']);
    $detailPage = makeContextPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'nav_hidden' => true,
    ]);

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $detailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    bindContextRuntime($site, $locale, $archivePage, '/blog');

    $captured = executeContextViewHelper(['as' => 'dataset']);

    expect($captured['dataset'])->toBeInstanceOf(DatasetContextObject::class)
        ->and($captured['dataset']->type())->toBe('blog')
        ->and($captured['dataset']->listUrl())->toBe('/blog')
        ->and($captured['dataset']->page())->toBe(1)
        ->and($captured['dataset']->search())->toBe('');
});

test('builds archive urls from current filter state', function (): void {
    $site = makeContextTestSite();
    $locale = makeContextLocale($site);
    $type = makeContextType('blog');
    $archivePage = makeContextPage($site, ['slug' => 'blog']);
    $detailPage = makeContextPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'nav_hidden' => true,
    ]);

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $detailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    bindContextRuntime($site, $locale, $archivePage, '/blog/category/news/page/2', ['q' => 'laravel']);
    app()->instance('cms.dataset_filters', ['category' => 'news', 'page' => 2]);

    $captured = executeContextViewHelper(['as' => 'dataset']);
    $dataset = $captured['dataset'];

    expect($dataset->currentUrl())->toBe('/blog/category/news/page/2?q=laravel')
        ->and($dataset->url(category: 'updates'))->toBe('/blog/category/updates?q=laravel')
        ->and($dataset->url(page: 3))->toBe('/blog/category/news/page/3?q=laravel')
        ->and($dataset->url(search: null))->toBe('/blog/category/news')
        ->and($dataset->listUrl())->toBe('/blog');
});

test('binds dataset type context for nested items and categories helpers', function (): void {
    $site = makeContextTestSite();
    $locale = makeContextLocale($site);
    $type = makeContextType('blog');
    $archivePage = makeContextPage($site, ['slug' => 'blog']);
    $detailPage = makeContextPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'nav_hidden' => true,
    ]);
    $category = makeContextCategory($type, 'news', 'News');
    $entry = makeContextEntry($type, 'hello-world', 'Hello World');
    $entry->categories()->attach($category->id);

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $detailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    bindContextRuntime($site, $locale, $archivePage, '/blog');

    $nested = [
        'items' => [],
        'categories' => [],
    ];

    executeContextViewHelper(
        ['as' => 'dataset'],
        function () use (&$nested): void {
            app(DatasetItemsViewHelper::class)->execute(
                ['as' => 'post'],
                function (array $vars) use (&$nested): string {
                    $nested['items'][] = $vars['post'];

                    return '';
                }
            );

            app(DatasetCategoriesViewHelper::class)->execute(
                ['as' => 'categories'],
                function (array $vars) use (&$nested): string {
                    $nested['categories'] = $vars['categories'];

                    return '';
                }
            );
        }
    );

    expect($nested['items'])->toHaveCount(1)
        ->and($nested['items'][0])->toBeInstanceOf(DatasetObject::class)
        ->and($nested['items'][0]->slug)->toBe('hello-world')
        ->and($nested['categories'])->toHaveCount(1)
        ->and($nested['categories'][0])->toBeInstanceOf(DatasetCategoryObject::class)
        ->and($nested['categories'][0]->slug)->toBe('news');
});
