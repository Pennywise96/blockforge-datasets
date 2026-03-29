<?php

use Blockforge\Cms\Config\Page;
use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Models\CmsSiteLocale;
use Blockforge\Datasets\Elements\DatasetObject;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetDetailPage;
use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\ViewHelpers\DatasetItemsViewHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function bindItemsRuntimeContext(CmsSite $site, CmsSiteLocale $locale): void
{
    app()->instance(Page::class, Page::make($site->handle)->domain('items.test'));
    app()->instance(CmsSite::class, $site);
    app()->instance('cms.locale', $locale->locale);
    app()->instance('cms.locale_id', $locale->id);
    app()->instance('cms.site_locale', $locale);
    app()->instance('cms.site_locales', new Collection([$locale]));
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

test('falls back to legacy current-page detail_page_id links when no canonical mapping exists', function (): void {
    $site = makeItemsTestSite();
    $locale = makeItemsLocale($site);
    $type = makeItemsType('blog');
    makeItemsEntry($type, 'legacy-post', 'Legacy Post');

    $detailPage = makeItemsPage($site, ['slug' => 'blog/detail']);
    $listPage = makeItemsPage($site, [
        'slug' => 'blog',
        'detail_page_id' => $detailPage->id,
    ]);

    bindItemsRuntimeContext($site, $locale);
    app()->instance(CmsPage::class, $listPage);

    $captured = executeItemsViewHelper(['type' => 'blog', 'as' => 'post']);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['post']->url())->toBe('/blog/legacy-post');
});
