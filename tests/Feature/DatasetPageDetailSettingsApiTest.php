<?php

use App\Models\User;
use Blockforge\Cms\Enums\PageDoktype;
use Blockforge\Cms\Http\Middleware\ResolveCmsSite;
use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Datasets\Models\CmsDatasetDetailPage;
use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ResolveCmsSite::class);
    $this->actingAs(User::factory()->create());
});

function makeDatasetDetailSite(): CmsSite
{
    return CmsSite::query()->create([
        'handle' => 'dataset-detail-'.uniqid(),
        'name' => 'Dataset Detail Test',
        'active' => true,
    ]);
}

function makeDatasetDetailPage(CmsSite $site, array $attributes = []): CmsPage
{
    return CmsPage::query()->create(array_merge([
        'site_id' => $site->id,
        'slug' => 'test-page',
        'sort_order' => 0,
        'status' => 'published',
    ], $attributes));
}

function makeDatasetDetailType(string $code = 'blog'): CmsDatasetType
{
    return CmsDatasetType::query()->create([
        'name' => ucfirst($code),
        'code' => $code,
    ]);
}

test('can mark a page as the canonical dataset detail page', function (): void {
    $site = makeDatasetDetailSite();
    $archivePage = makeDatasetDetailPage($site, ['slug' => 'blog']);
    $detailPage = makeDatasetDetailPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'nav_hidden' => false,
    ]);
    $type = makeDatasetDetailType('blog');

    $this->putJson("/api/cms/datasets/pages/{$detailPage->id}/detail-settings", [
        'is_dataset_detail_page' => true,
        'dataset_detail_type_id' => $type->id,
    ])->assertOk()
        ->assertJsonPath('is_dataset_detail_page', true)
        ->assertJsonPath('dataset_detail_type_id', $type->id)
        ->assertJsonPath('dataset_detail_type_code', 'blog');

    expect(CmsDatasetDetailPage::query()->where('page_id', $detailPage->id)->exists())->toBeTrue()
        ->and($detailPage->fresh()->nav_hidden)->toBeTrue();
});

test('can clear canonical dataset detail page settings', function (): void {
    $site = makeDatasetDetailSite();
    $archivePage = makeDatasetDetailPage($site, ['slug' => 'blog']);
    $detailPage = makeDatasetDetailPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
    ]);
    $type = makeDatasetDetailType('blog');

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $detailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    $this->putJson("/api/cms/datasets/pages/{$detailPage->id}/detail-settings", [
        'is_dataset_detail_page' => false,
        'dataset_detail_type_id' => null,
    ])->assertOk()
        ->assertJsonPath('is_dataset_detail_page', false)
        ->assertJsonPath('dataset_detail_type_id', null);

    expect(CmsDatasetDetailPage::query()->where('page_id', $detailPage->id)->exists())->toBeFalse();
});

test('rejects marking a root page as a canonical dataset detail page', function (): void {
    $site = makeDatasetDetailSite();
    $detailPage = makeDatasetDetailPage($site, ['slug' => 'blog/detail']);
    $type = makeDatasetDetailType('blog');

    $this->putJson("/api/cms/datasets/pages/{$detailPage->id}/detail-settings", [
        'is_dataset_detail_page' => true,
        'dataset_detail_type_id' => $type->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['is_dataset_detail_page']);
});

test('rejects non-standard pages as canonical dataset detail pages', function (): void {
    $site = makeDatasetDetailSite();
    $archivePage = makeDatasetDetailPage($site, ['slug' => 'blog']);
    $detailPage = makeDatasetDetailPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
        'doktype' => PageDoktype::Shortcut,
    ]);
    $type = makeDatasetDetailType('blog');

    $this->putJson("/api/cms/datasets/pages/{$detailPage->id}/detail-settings", [
        'is_dataset_detail_page' => true,
        'dataset_detail_type_id' => $type->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['is_dataset_detail_page']);
});

test('rejects a second canonical detail page for the same dataset type in one site', function (): void {
    $site = makeDatasetDetailSite();
    $archivePage = makeDatasetDetailPage($site, ['slug' => 'blog']);
    $firstDetailPage = makeDatasetDetailPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $archivePage->id,
    ]);
    $secondDetailPage = makeDatasetDetailPage($site, [
        'slug' => 'blog/detail-2',
        'parent_id' => $archivePage->id,
    ]);
    $type = makeDatasetDetailType('blog');

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $firstDetailPage->id,
        'dataset_type_id' => $type->id,
    ]);

    $this->putJson("/api/cms/datasets/pages/{$secondDetailPage->id}/detail-settings", [
        'is_dataset_detail_page' => true,
        'dataset_detail_type_id' => $type->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['dataset_detail_type_id']);
});

test('rejects a second canonical detail page under the same archive page', function (): void {
    $site = makeDatasetDetailSite();
    $archivePage = makeDatasetDetailPage($site, ['slug' => 'archive']);
    $firstDetailPage = makeDatasetDetailPage($site, [
        'slug' => 'archive/blog-detail',
        'parent_id' => $archivePage->id,
    ]);
    $secondDetailPage = makeDatasetDetailPage($site, [
        'slug' => 'archive/news-detail',
        'parent_id' => $archivePage->id,
    ]);
    $blogType = makeDatasetDetailType('blog');
    $newsType = makeDatasetDetailType('news');

    CmsDatasetDetailPage::query()->create([
        'site_id' => $site->id,
        'page_id' => $firstDetailPage->id,
        'dataset_type_id' => $blogType->id,
    ]);

    $this->putJson("/api/cms/datasets/pages/{$secondDetailPage->id}/detail-settings", [
        'is_dataset_detail_page' => true,
        'dataset_detail_type_id' => $newsType->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['is_dataset_detail_page']);
});

test('moving a canonical detail page syncs its archive mapping', function (): void {
    $site = makeDatasetDetailSite();
    app()->instance(CmsSite::class, $site);

    $firstArchivePage = makeDatasetDetailPage($site, ['slug' => 'blog']);
    $secondArchivePage = makeDatasetDetailPage($site, ['slug' => 'news']);
    $detailPage = makeDatasetDetailPage($site, [
        'slug' => 'blog/detail',
        'parent_id' => $firstArchivePage->id,
    ]);
    $type = makeDatasetDetailType('blog');

    $this->putJson("/api/cms/datasets/pages/{$detailPage->id}/detail-settings", [
        'is_dataset_detail_page' => true,
        'dataset_detail_type_id' => $type->id,
    ])->assertOk();

    $this->putJson("/api/cms/pages/{$detailPage->id}/move", [
        'parent_id' => $secondArchivePage->id,
        'sort_order' => 0,
    ])->assertOk();

    expect(CmsDatasetDetailPage::query()->where('page_id', $detailPage->id)->value('archive_page_id'))
        ->toBe($secondArchivePage->id);
});

test('moving a canonical detail page into an occupied archive is rejected', function (): void {
    $site = makeDatasetDetailSite();
    app()->instance(CmsSite::class, $site);

    $blogArchivePage = makeDatasetDetailPage($site, ['slug' => 'blog']);
    $newsArchivePage = makeDatasetDetailPage($site, ['slug' => 'news']);
    $blogDetailPage = makeDatasetDetailPage($site, [
        'slug' => 'blog/blog-detail',
        'parent_id' => $blogArchivePage->id,
    ]);
    $newsDetailPage = makeDatasetDetailPage($site, [
        'slug' => 'news/news-detail',
        'parent_id' => $newsArchivePage->id,
    ]);
    $blogType = makeDatasetDetailType('blog');
    $newsType = makeDatasetDetailType('news');

    $this->putJson("/api/cms/datasets/pages/{$blogDetailPage->id}/detail-settings", [
        'is_dataset_detail_page' => true,
        'dataset_detail_type_id' => $blogType->id,
    ])->assertOk();

    $this->putJson("/api/cms/datasets/pages/{$newsDetailPage->id}/detail-settings", [
        'is_dataset_detail_page' => true,
        'dataset_detail_type_id' => $newsType->id,
    ])->assertOk();

    $this->putJson("/api/cms/pages/{$blogDetailPage->id}/move", [
        'parent_id' => $newsArchivePage->id,
        'sort_order' => 0,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);

    expect(CmsDatasetDetailPage::query()->where('page_id', $blogDetailPage->id)->value('archive_page_id'))
        ->toBe($blogArchivePage->id);
});
