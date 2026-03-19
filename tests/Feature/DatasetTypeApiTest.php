<?php

use App\Models\User;
use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('can list dataset types', function (): void {
    CmsDatasetType::query()->create(['name' => 'Blog', 'slug' => 'blog']);
    CmsDatasetType::query()->create(['name' => 'Treatments', 'slug' => 'treatments']);

    $this->getJson('/api/cms/datasets/types')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.slug', 'blog');
});

test('can create a dataset type', function (): void {
    $this->postJson('/api/cms/datasets/types', [
        'name' => 'Blog',
        'slug' => 'blog',
    ])->assertCreated()
        ->assertJsonPath('name', 'Blog')
        ->assertJsonPath('slug', 'blog');

    expect(CmsDatasetType::query()->where('slug', 'blog')->exists())->toBeTrue();
});

test('create type requires name', function (): void {
    $this->postJson('/api/cms/datasets/types', ['slug' => 'blog'])
        ->assertUnprocessable();
});

test('create type requires unique slug', function (): void {
    CmsDatasetType::query()->create(['name' => 'Blog', 'slug' => 'blog']);

    $this->postJson('/api/cms/datasets/types', ['name' => 'Blog 2', 'slug' => 'blog'])
        ->assertUnprocessable();
});

test('can update a dataset type', function (): void {
    $type = CmsDatasetType::query()->create(['name' => 'Blog', 'slug' => 'blog']);

    $this->putJson("/api/cms/datasets/types/{$type->id}", [
        'name' => 'Articles',
        'description' => 'Article collection',
    ])->assertOk()
        ->assertJsonPath('name', 'Articles')
        ->assertJsonPath('description', 'Article collection');
});

test('can delete a dataset type', function (): void {
    $type = CmsDatasetType::query()->create(['name' => 'Blog', 'slug' => 'blog']);

    $this->deleteJson("/api/cms/datasets/types/{$type->id}")
        ->assertNoContent();

    expect(CmsDatasetType::query()->find($type->id))->toBeNull();
});

test('returns 404 for missing type', function (): void {
    $this->deleteJson('/api/cms/datasets/types/99999')
        ->assertNotFound();
});
