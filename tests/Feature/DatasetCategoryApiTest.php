<?php

use App\Models\User;
use Blockforge\Cms\Http\Middleware\ResolveCmsSite;
use Blockforge\Datasets\Models\CmsDatasetCategory;
use Blockforge\Datasets\Models\CmsDatasetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ResolveCmsSite::class);
    $this->actingAs(User::factory()->create());
});

function makeType(string $code = 'blog'): CmsDatasetType
{
    return CmsDatasetType::query()->firstOrCreate(
        ['code' => $code],
        ['name' => ucfirst($code)],
    );
}

test('can list categories for a type', function (): void {
    $type = makeType();
    CmsDatasetCategory::query()->create(['type_id' => $type->id, 'name' => 'Tech', 'slug' => 'tech']);
    CmsDatasetCategory::query()->create(['type_id' => $type->id, 'name' => 'Health', 'slug' => 'health']);

    $other = makeType('news');
    CmsDatasetCategory::query()->create(['type_id' => $other->id, 'name' => 'Sports', 'slug' => 'sports']);

    $this->getJson("/api/cms/datasets/types/{$type->id}/categories")
        ->assertOk()
        ->assertJsonCount(2);
});

test('can create a category for a type', function (): void {
    $type = makeType();

    $this->postJson("/api/cms/datasets/types/{$type->id}/categories", [
        'name' => 'Tech',
        'slug' => 'tech',
    ])->assertCreated()
        ->assertJsonPath('name', 'Tech')
        ->assertJsonPath('type_id', $type->id);
});

test('create category requires name and slug', function (): void {
    $type = makeType();

    $this->postJson("/api/cms/datasets/types/{$type->id}/categories", [])
        ->assertUnprocessable();
});

test('can create a nested category', function (): void {
    $type = makeType();
    $parent = CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'name' => 'Tech',
        'slug' => 'tech',
    ]);

    $this->postJson("/api/cms/datasets/types/{$type->id}/categories", [
        'name' => 'AI',
        'slug' => 'ai',
        'parent_id' => $parent->id,
    ])->assertCreated()
        ->assertJsonPath('parent_id', $parent->id);
});

test('cannot create a category under a parent from another type', function (): void {
    $type = makeType();
    $otherType = makeType('news');
    $foreignParent = CmsDatasetCategory::query()->create([
        'type_id' => $otherType->id,
        'name' => 'Foreign',
        'slug' => 'foreign',
    ]);

    $this->postJson("/api/cms/datasets/types/{$type->id}/categories", [
        'name' => 'AI',
        'slug' => 'ai',
        'parent_id' => $foreignParent->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('can update a category', function (): void {
    $type = makeType();
    $category = CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'name' => 'Tech',
        'slug' => 'tech',
    ]);

    $this->putJson("/api/cms/datasets/categories/{$category->id}", [
        'name' => 'Technology',
    ])->assertOk()
        ->assertJsonPath('name', 'Technology');
});

test('can move a category under another category', function (): void {
    $type = makeType();
    $parent = CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'name' => 'Tech',
        'slug' => 'tech',
        'sort_order' => 0,
    ]);
    $category = CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'name' => 'AI',
        'slug' => 'ai',
        'sort_order' => 0,
    ]);

    $this->putJson("/api/cms/datasets/categories/{$category->id}", [
        'parent_id' => $parent->id,
        'sort_order' => 1,
    ])->assertOk()
        ->assertJsonPath('parent_id', $parent->id)
        ->assertJsonPath('sort_order', 1);
});

test('cannot move a category under a parent from another type', function (): void {
    $type = makeType();
    $otherType = makeType('news');
    $category = CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'name' => 'AI',
        'slug' => 'ai',
    ]);
    $foreignParent = CmsDatasetCategory::query()->create([
        'type_id' => $otherType->id,
        'name' => 'Foreign',
        'slug' => 'foreign',
    ]);

    $this->putJson("/api/cms/datasets/categories/{$category->id}", [
        'parent_id' => $foreignParent->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('cannot move a category under itself or one of its descendants', function (): void {
    $type = makeType();
    $parent = CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'name' => 'Tech',
        'slug' => 'tech',
    ]);
    $child = CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'parent_id' => $parent->id,
        'name' => 'AI',
        'slug' => 'ai',
    ]);

    $this->putJson("/api/cms/datasets/categories/{$parent->id}", [
        'parent_id' => $parent->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);

    $this->putJson("/api/cms/datasets/categories/{$parent->id}", [
        'parent_id' => $child->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('can delete a category', function (): void {
    $type = makeType();
    $category = CmsDatasetCategory::query()->create([
        'type_id' => $type->id,
        'name' => 'Tech',
        'slug' => 'tech',
    ]);

    $this->deleteJson("/api/cms/datasets/categories/{$category->id}")
        ->assertNoContent();

    expect(CmsDatasetCategory::query()->find($category->id))->toBeNull();
});
