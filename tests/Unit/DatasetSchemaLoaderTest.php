<?php

use Blockforge\Cms\Config\Page;
use Blockforge\Cms\Fields\TextInput;
use Blockforge\Datasets\Schemas\DatasetSchema;
use Blockforge\Datasets\Schemas\DatasetSchemaLoader;
use Illuminate\Filesystem\Filesystem;

it('loads schemas from mixed page-registered sources', function (): void {
    $publicPath = sys_get_temp_dir().'/blockforge-dataset-schema-'.uniqid();
    mkdir($publicPath.'/fileadmin/datasets/room', 0777, true);
    mkdir($publicPath.'/fileadmin/datasets/offer', 0777, true);

    file_put_contents(
        $publicPath.'/fileadmin/datasets/room/config.php',
        <<<'PHP'
<?php

use Blockforge\Cms\Fields\TextInput;
use Blockforge\Datasets\Schemas\DatasetSchema;

return DatasetSchema::make('room')->fields([
    TextInput::make('room_code')->label('Room Code'),
]);
PHP
    );

    file_put_contents(
        $publicPath.'/fileadmin/datasets/offer/config.php',
        <<<'PHP'
<?php

use Blockforge\Cms\Fields\TextInput;
use Blockforge\Datasets\Schemas\DatasetSchema;

return DatasetSchema::make('offer')->fields([
    TextInput::make('headline')->label('Headline')->translatable(),
]);
PHP
    );

    $originalPublicPath = public_path();
    app()->usePublicPath($publicPath);

    $page = Page::make('main')->registerDatasetSchemas([
        'fileadmin/datasets',
        DatasetSchema::make('inline')->fields([
            TextInput::make('internal_code')->label('Internal Code'),
        ]),
    ]);

    $schemas = app(DatasetSchemaLoader::class)->loadForPage($page);

    app()->usePublicPath($originalPublicPath);
    (new Filesystem)->deleteDirectory($publicPath);

    expect($schemas)->toHaveKeys(['room', 'offer', 'inline'])
        ->and($schemas['room']->getCode())->toBe('room')
        ->and($schemas['inline']->getFields())->toHaveCount(1);
});

it('prefers inline schemas over file-based schemas for the same code', function (): void {
    $publicPath = sys_get_temp_dir().'/blockforge-dataset-schema-'.uniqid();
    mkdir($publicPath.'/fileadmin/datasets/room', 0777, true);

    file_put_contents(
        $publicPath.'/fileadmin/datasets/room/config.php',
        <<<'PHP'
<?php

use Blockforge\Cms\Fields\TextInput;
use Blockforge\Datasets\Schemas\DatasetSchema;

return DatasetSchema::make('room')->fields([
    TextInput::make('legacy_field')->label('Legacy Field'),
]);
PHP
    );

    $originalPublicPath = public_path();
    app()->usePublicPath($publicPath);

    $page = Page::make('main')->registerDatasetSchemas([
        'fileadmin/datasets',
        DatasetSchema::make('room')->fields([
            TextInput::make('room_code')->label('Room Code'),
        ]),
    ]);

    $schemas = app(DatasetSchemaLoader::class)->loadForPage($page);
    $schema = $schemas['room'] ?? null;

    app()->usePublicPath($originalPublicPath);
    (new Filesystem)->deleteDirectory($publicPath);

    expect($schema)->not->toBeNull()
        ->and($schema?->getFields())->toHaveCount(1)
        ->and($schema?->getFields()[0])->toBeInstanceOf(TextInput::class)
        ->and($schema?->getFields()[0]->getName())->toBe('room_code');
});
