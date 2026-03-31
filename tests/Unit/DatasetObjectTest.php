<?php

use Blockforge\Cms\Elements\MediaItemObject;
use Blockforge\Datasets\Elements\DatasetObject;

it('exposes fixed fields via magic property access', function (): void {
    $obj = new DatasetObject(
        fields: ['title' => 'Hello', 'slug' => 'hello', 'visibility_mode' => 'always', 'is_visible_now' => true],
    );

    expect($obj->title)->toBe('Hello')
        ->and($obj->slug)->toBe('hello')
        ->and($obj->visibility_mode)->toBe('always')
        ->and($obj->is_visible_now)->toBeTrue()
        ->and($obj->excerpt)->toBeNull();
});

it('falls back to default values for missing fixed fields', function (): void {
    $obj = new DatasetObject;

    expect($obj->id)->toBeNull()
        ->and($obj->type)->toBe('')
        ->and($obj->slug)->toBe('')
        ->and($obj->visibility_mode)->toBe('disabled')
        ->and($obj->is_visible_now)->toBeFalse()
        ->and($obj->title)->toBe('')
        ->and($obj->content)->toBeNull();
});

it('exposes translatable extra field values via magic access', function (): void {
    $obj = new DatasetObject(
        translatedFieldValues: ['subtitle' => 'Sub', 'duration' => '45 min'],
    );

    expect($obj->subtitle)->toBe('Sub')
        ->and($obj->duration)->toBe('45 min');
});

it('exposes non-translatable field values via magic access', function (): void {
    $obj = new DatasetObject(
        fieldValues: ['price' => 99, 'featured' => true],
    );

    expect($obj->price)->toBe(99)
        ->and($obj->featured)->toBeTrue();
});

it('translated field values take priority over non-translatable field values', function (): void {
    $obj = new DatasetObject(
        fieldValues: ['key' => 'from-field-values'],
        translatedFieldValues: ['key' => 'from-translated-field-values'],
    );

    expect($obj->key)->toBe('from-translated-field-values');
});

it('returns null for unknown properties', function (): void {
    $obj = new DatasetObject;

    expect($obj->nonExistentField)->toBeNull();
});

it('isset returns true for defined fields', function (): void {
    $obj = new DatasetObject(
        fields: ['title' => 'Test'],
        translatedFieldValues: ['subtitle' => 'Sub'],
        fieldValues: ['price' => 10],
    );

    expect(isset($obj->title))->toBeTrue()
        ->and(isset($obj->subtitle))->toBeTrue()
        ->and(isset($obj->price))->toBeTrue()
        ->and(isset($obj->unknown))->toBeFalse();
});

it('get() retrieves extra field with default fallback', function (): void {
    $obj = new DatasetObject(
        translatedFieldValues: ['color' => 'blue'],
    );

    expect($obj->get('color'))->toBe('blue')
        ->and($obj->get('missing', 'default'))->toBe('default');
});

it('resolves dataset image arrays to media item objects', function (): void {
    app()->instance('cms.locale', 'en');

    $obj = new DatasetObject(
        translatedFieldValues: [
            'image' => [
                'id' => 7,
                'disk' => 'public',
                'path' => 'media/blog/cover.jpg',
                'filename' => 'cover.jpg',
                'mime_type' => 'image/jpeg',
                'width' => 1600,
                'height' => 900,
                'focal_x' => 0.25,
                'focal_y' => 0.75,
                'translations' => [
                    'en' => [
                        'alt' => 'Cover image',
                        'title' => 'Cover',
                    ],
                ],
            ],
        ],
    );

    expect($obj->image)->toBeInstanceOf(MediaItemObject::class)
        ->and($obj->image->media_item_id)->toBe(7)
        ->and($obj->image->alt)->toBe('Cover image')
        ->and($obj->image->title)->toBe('Cover')
        ->and($obj->image->objectPosition())->toBe('25% 75%');
});

it('exposes categories array', function (): void {
    $obj = new DatasetObject(
        categories: [
            ['id' => 1, 'name' => 'News', 'slug' => 'news'],
            ['id' => 2, 'name' => 'Tips', 'slug' => 'tips'],
        ],
    );

    expect($obj->categories())->toHaveCount(2)
        ->and($obj->categories()[0]['slug'])->toBe('news');
});

it('hasCategory returns true for matching slug', function (): void {
    $obj = new DatasetObject(
        categories: [
            ['id' => 1, 'name' => 'News', 'slug' => 'news'],
        ],
    );

    expect($obj->hasCategory('news'))->toBeTrue()
        ->and($obj->hasCategory('sport'))->toBeFalse();
});

it('date() returns null when no date-like value exists', function (): void {
    $obj = new DatasetObject;

    expect($obj->date())->toBeNull();
});

it('url() returns null when no detailBase is set', function (): void {
    $obj = new DatasetObject(fields: ['slug' => 'artikel-1']);

    expect($obj->url())->toBeNull();
});

it('url() returns full URL when detailBase is set', function (): void {
    $obj = new DatasetObject(
        fields: ['slug' => 'artikel-1'],
        detailBase: '/blog',
    );

    expect($obj->url())->toBe('/blog/artikel-1');
});

it('url() returns null for empty slug even with detailBase', function (): void {
    $obj = new DatasetObject(
        fields: ['slug' => ''],
        detailBase: '/blog',
    );

    expect($obj->url())->toBeNull();
});

it('url() strips trailing slash from detailBase', function (): void {
    $obj = new DatasetObject(
        fields: ['slug' => 'artikel-1'],
        detailBase: '/blog/',
    );

    expect($obj->url())->toBe('/blog/artikel-1');
});
