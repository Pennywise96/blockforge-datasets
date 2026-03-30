<?php

use Blockforge\Cms\Config\Page;
use Blockforge\Cms\Http\Middleware\ResolveCmsPage;
use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Models\CmsSiteLocale;
use Blockforge\Datasets\Models\CmsDataset;
use Blockforge\Datasets\Models\CmsDatasetDetailPage;
use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeCanonicalRoutingSite(): CmsSite
{
    return CmsSite::query()->create([
        'handle' => 'canonical-routing-'.uniqid(),
        'name' => 'Canonical Routing Test',
        'active' => true,
    ]);
}

function makeCanonicalRoutingLocale(CmsSite $site): CmsSiteLocale
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

function makeCanonicalRoutingPage(CmsSite $site, array $attributes = []): CmsPage
{
    return CmsPage::query()->create(array_merge([
        'site_id' => $site->id,
        'slug' => 'test-page',
        'sort_order' => 0,
        'status' => 'published',
    ], $attributes));
}

function makeCanonicalRoutingType(string $code = 'blog'): CmsDatasetType
{
    return CmsDatasetType::query()->create([
        'name' => ucfirst($code),
        'code' => $code,
    ]);
}

function makeCanonicalRoutingEntry(CmsDatasetType $type, string $slug): CmsDataset
{
    $dataset = CmsDataset::query()->create([
        'type_id' => $type->id,
        'slug' => $slug,
        'visibility_mode' => 'always',
    ]);

    $dataset->translations()->create([
        'locale' => 'en',
        'title' => ucfirst(str_replace('-', ' ', $slug)),
    ]);

    return $dataset;
}

/**
 * @param  Collection<int, CmsSiteLocale>  $siteLocales
 */
function invokeCanonicalRouting(string $path, CmsSite $site, CmsSiteLocale $locale, Collection $siteLocales): int
{
    $siteConfig = Page::make($site->handle)->domain('detail.test');
    app()->instance(Page::class, $siteConfig);
    app()->instance(CmsSite::class, $site);
    app()->instance('cms.locale', $locale->locale);
    app()->instance('cms.locale_id', $locale->id);
    app()->instance('cms.site_locale', $locale);
    app()->instance('cms.site_locales', $siteLocales);

    try {
        app(ResolveCmsPage::class)->handle(
            Request::create($path),
            fn () => response('ok'),
        );

        return 200;
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }
}

test('canonical dataset detail routing resolves the configured detail page', function (): void {
    $site = makeCanonicalRoutingSite();
    $locale = makeCanonicalRoutingLocale($site);
    $siteLocales = new Collection([$locale]);
    $type = makeCanonicalRoutingType('blog');
    makeCanonicalRoutingEntry($type, 'hello-world');

    $archivePage = makeCanonicalRoutingPage($site, ['slug' => 'blog']);
    $detailPage = makeCanonicalRoutingPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'nav_hidden' => true,
    ]);

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $detailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    $status = invokeCanonicalRouting('/blog/hello-world', $site, $locale, $siteLocales);

    expect($status)->toBe(200)
        ->and(app(CmsPage::class)->id)->toBe($detailPage->id)
        ->and(app('cms.dataset_slug'))->toBe('hello-world')
        ->and(app('cms.dataset_type'))->toBeInstanceOf(CmsDatasetType::class)
        ->and(app('cms.dataset_type')->id)->toBe($type->id)
        ->and(app('cms.dataset_list_page')->id)->toBe($archivePage->id)
        ->and(app('cms.dataset_detail_page')->id)->toBe($detailPage->id);
});

test('canonical dataset archive category route resolves the archive page and binds filters', function (): void {
    $site = makeCanonicalRoutingSite();
    $locale = makeCanonicalRoutingLocale($site);
    $siteLocales = new Collection([$locale]);
    $type = makeCanonicalRoutingType('blog');

    $archivePage = makeCanonicalRoutingPage($site, ['slug' => 'blog']);
    $detailPage = makeCanonicalRoutingPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'nav_hidden' => true,
    ]);

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $detailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    $status = invokeCanonicalRouting('/blog/category/news', $site, $locale, $siteLocales);

    expect($status)->toBe(200)
        ->and(app(CmsPage::class)->id)->toBe($archivePage->id)
        ->and(app('cms.dataset_type')->id)->toBe($type->id)
        ->and(app('cms.dataset_filters'))->toBe([
            'category' => 'news',
        ]);
});

test('canonical dataset archive pagination route resolves the archive page and binds filters', function (): void {
    $site = makeCanonicalRoutingSite();
    $locale = makeCanonicalRoutingLocale($site);
    $siteLocales = new Collection([$locale]);
    $type = makeCanonicalRoutingType('blog');

    $archivePage = makeCanonicalRoutingPage($site, ['slug' => 'blog']);
    $detailPage = makeCanonicalRoutingPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'nav_hidden' => true,
    ]);

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $detailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    $status = invokeCanonicalRouting('/blog/category/news/page/2', $site, $locale, $siteLocales);

    expect($status)->toBe(200)
        ->and(app(CmsPage::class)->id)->toBe($archivePage->id)
        ->and(app('cms.dataset_filters'))->toBe([
            'category' => 'news',
            'page' => 2,
        ]);
});

test('exact page matches still win over canonical dataset detail routing', function (): void {
    $site = makeCanonicalRoutingSite();
    $locale = makeCanonicalRoutingLocale($site);
    $siteLocales = new Collection([$locale]);
    $type = makeCanonicalRoutingType('blog');
    makeCanonicalRoutingEntry($type, 'hello-world');

    $archivePage = makeCanonicalRoutingPage($site, ['slug' => 'blog']);
    $exactMatchPage = makeCanonicalRoutingPage($site, ['slug' => 'blog/hello-world']);

    $canonicalDetailPage = makeCanonicalRoutingPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'nav_hidden' => true,
    ]);

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $canonicalDetailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    $status = invokeCanonicalRouting('/blog/hello-world', $site, $locale, $siteLocales);

    expect($status)->toBe(200)
        ->and(app(CmsPage::class)->id)->toBe($exactMatchPage->id)
        ->and(app()->bound('cms.dataset_type'))->toBeFalse();
});

test('canonical dataset detail routing returns 404 when no canonical detail page is configured', function (): void {
    $site = makeCanonicalRoutingSite();
    $locale = makeCanonicalRoutingLocale($site);
    $siteLocales = new Collection([$locale]);
    $type = makeCanonicalRoutingType('blog');
    makeCanonicalRoutingEntry($type, 'hello-world');
    makeCanonicalRoutingPage($site, ['slug' => 'blog']);

    $status = invokeCanonicalRouting('/blog/hello-world', $site, $locale, $siteLocales);

    expect($status)->toBe(404);
});
