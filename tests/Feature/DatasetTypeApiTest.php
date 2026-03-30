<?php

use App\Models\User;
use Blockforge\Cms\Http\Middleware\ResolveCmsSite;
use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ResolveCmsSite::class);
    $this->actingAs(User::factory()->create());
});

test('can list dataset types', function (): void {
    CmsDatasetType::query()->create(['name' => 'Blog', 'code' => 'blog']);
    CmsDatasetType::query()->create(['name' => 'Treatments', 'code' => 'treatments']);

    $this->getJson('/api/cms/datasets/types')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.code', 'blog')
        ->assertJsonPath('0.schema_status', 'missing');
});

test('can create a dataset type', function (): void {
    $this->postJson('/api/cms/datasets/types', [
        'name' => 'Blog',
        'code' => 'blog',
    ])->assertCreated()
        ->assertJsonPath('name', 'Blog')
        ->assertJsonPath('code', 'blog')
        ->assertJsonPath('schema_status', 'missing');

    expect(CmsDatasetType::query()->where('code', 'blog')->exists())->toBeTrue();
});

test('create type requires name', function (): void {
    $this->postJson('/api/cms/datasets/types', ['code' => 'blog'])
        ->assertUnprocessable();
});

test('create type requires unique code', function (): void {
    CmsDatasetType::query()->create(['name' => 'Blog', 'code' => 'blog']);

    $this->postJson('/api/cms/datasets/types', ['name' => 'Blog 2', 'code' => 'blog'])
        ->assertUnprocessable();
});

test('can update a dataset type', function (): void {
    $type = CmsDatasetType::query()->create(['name' => 'Blog', 'code' => 'blog']);

    $this->putJson("/api/cms/datasets/types/{$type->id}", [
        'name' => 'Articles',
        'code' => 'article',
        'description' => 'Article collection',
    ])->assertOk()
        ->assertJsonPath('name', 'Articles')
        ->assertJsonPath('code', 'article')
        ->assertJsonPath('description', 'Article collection');
});

test('can delete a dataset type', function (): void {
    $type = CmsDatasetType::query()->create(['name' => 'Blog', 'code' => 'blog']);

    $this->deleteJson("/api/cms/datasets/types/{$type->id}")
        ->assertNoContent();

    expect(CmsDatasetType::query()->find($type->id))->toBeNull();
});

test('returns 404 for missing type', function (): void {
    $this->deleteJson('/api/cms/datasets/types/99999')
        ->assertNotFound();
});
