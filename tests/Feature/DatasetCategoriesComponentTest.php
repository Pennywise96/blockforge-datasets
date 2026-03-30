<?php

use Blockforge\Cms\Config\Page;
use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Models\CmsSiteLocale;
use Blockforge\Datasets\Elements\DatasetCategoryObject;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetDetailPage;
use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\ViewHelpers\DatasetCategoriesViewHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeCategoryTestSite(): CmsSite
{
    return CmsSite::query()->create([
        'handle' => 'categories-test-'.uniqid(),
        'name' => 'Categories Test',
        'domain' => 'categories.test',
        'active' => true,
    ]);
}

function makeCategoryLocale(CmsSite $site): CmsSiteLocale
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

function makeCategoryType(string $slug = 'blog'): CmsDatasetType
{
    return CmsDatasetType::query()->create([
        'name' => ucfirst($slug),
        'slug' => $slug,
    ]);
}

function makeCategoryPage(CmsSite $site, array $attributes = []): CmsPage
{
    return CmsPage::query()->create(array_merge([
        'site_id' => $site->id,
        'slug' => 'test-page',
        'sort_order' => 0,
        'status' => 'published',
    ], $attributes));
}

function makeDatasetCategory(CmsDatasetType $type, string $slug, string $name, int $sortOrder = 0): CmsDatasetCategory
{
    return CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'name' => $name,
        'slug' => $slug,
        'sort_order' => $sortOrder,
    ]);
}

function makeCategoryEntry(CmsDatasetType $type, string $slug, string $title, string $status = 'published'): CmsDataset
{
    $dataset = CmsDataset::query()->create([
        'type_id' => $type->id,
        'slug' => $slug,
        'status' => $status,
    ]);

    $dataset->translations()->create([
        'locale' => 'en',
        'title' => $title,
    ]);

    return $dataset;
}

/** @return array<string, mixed> */
function executeCategoriesViewHelper(array $args): array
{
    $captured = [];

    app(DatasetCategoriesViewHelper::class)->execute(
        $args,
        function (array $vars) use (&$captured): string {
            $captured = $vars;

            return '';
        }
    );

    return $captured;
}

function bindCategoriesRuntimeContext(CmsSite $site, CmsSiteLocale $locale, string $path = '/', array $query = []): void
{
    app()->instance(Page::class, Page::make($site->handle)->domain('categories.test'));
    app()->instance(CmsSite::class, $site);
    app()->instance('cms.locale', $locale->locale);
    app()->instance('cms.locale_id', $locale->id);
    app()->instance('cms.site_locale', $locale);
    app()->instance('cms.site_locales', new Collection([$locale]));
    app()->instance('request', Request::create($path, 'GET', $query));
}

test('exposes categories as collection objects for a dataset type', function (): void {
    $site = makeCategoryTestSite();
    $locale = makeCategoryLocale($site);
    $type = makeCategoryType('blog');
    makeDatasetCategory($type, 'news', 'News', 2);
    makeDatasetCategory($type, 'updates', 'Updates', 1);

    bindCategoriesRuntimeContext($site, $locale);

    $captured = executeCategoriesViewHelper(['type' => 'blog']);

    expect($captured['categories'])->toHaveCount(2)
        ->and($captured['categories'][0])->toBeInstanceOf(DatasetCategoryObject::class)
        ->and($captured['categories'][0]->slug)->toBe('updates')
        ->and($captured['categories'][1]->slug)->toBe('news');
});

test('generates canonical category urls and marks the active category', function (): void {
    $site = makeCategoryTestSite();
    $locale = makeCategoryLocale($site);
    $type = makeCategoryType('blog');
    makeDatasetCategory($type, 'news', 'News');

    $archivePage = makeCategoryPage($site, ['slug' => 'blog']);
    $detailPage = makeCategoryPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'nav_hidden' => true,
    ]);

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $detailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    bindCategoriesRuntimeContext($site, $locale, '/blog/category/news', ['q' => 'laravel']);
    app()->instance('cms.dataset_filters', ['category' => 'news']);

    $captured = executeCategoriesViewHelper(['type' => 'blog']);
    $category = $captured['categories'][0];

    expect($category->url())->toBe('/blog/category/news?q=laravel')
        ->and($category->isActive())->toBeTrue();
});

test('can restrict the category collection to a provided subset', function (): void {
    $site = makeCategoryTestSite();
    $locale = makeCategoryLocale($site);
    $type = makeCategoryType('blog');
    makeDatasetCategory($type, 'news', 'News');
    makeDatasetCategory($type, 'updates', 'Updates');

    bindCategoriesRuntimeContext($site, $locale);

    $captured = executeCategoriesViewHelper([
        'type' => 'blog',
        'categories' => ['updates'],
        'as' => 'blogCategories',
    ]);

    expect($captured['blogCategories'])->toHaveCount(1)
        ->and($captured['blogCategories'][0]->slug)->toBe('updates');
});

test('can limit categories to used ones and expose published entry counts', function (): void {
    $site = makeCategoryTestSite();
    $locale = makeCategoryLocale($site);
    $type = makeCategoryType('blog');
    $news = makeDatasetCategory($type, 'news', 'News');
    $updates = makeDatasetCategory($type, 'updates', 'Updates');
    $unused = makeDatasetCategory($type, 'unused', 'Unused');

    $first = makeCategoryEntry($type, 'post-1', 'Post 1');
    $second = makeCategoryEntry($type, 'post-2', 'Post 2');
    $draft = makeCategoryEntry($type, 'post-3', 'Post 3', 'draft');

    $first->categories()->attach($news->id);
    $second->categories()->attach([$news->id, $updates->id]);
    $draft->categories()->attach($updates->id);

    bindCategoriesRuntimeContext($site, $locale);

    $captured = executeCategoriesViewHelper([
        'type' => 'blog',
        'onlyUsed' => true,
        'withCount' => true,
        'sortBy' => 'name',
    ]);

    expect($captured['categories'])->toHaveCount(2)
        ->and($captured['categories'][0]->slug)->toBe('news')
        ->and($captured['categories'][0]->count)->toBe(2)
        ->and($captured['categories'][1]->slug)->toBe('updates')
        ->and($captured['categories'][1]->count)->toBe(1)
        ->and(collect($captured['categories'])->contains(fn (DatasetCategoryObject $category) => $category->slug === $unused->slug))->toBeFalse();
});
