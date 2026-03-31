<?php

use Blockforge\Datasets\Providers\DatasetServiceProvider;

test('skips registering package views when the views directory is missing', function (): void {
    $missingPackageRoot = sys_get_temp_dir().'/blockforge-datasets-missing-views-'.uniqid();

    mkdir($missingPackageRoot, 0777, true);

    $provider = new class(app()) extends DatasetServiceProvider
    {
        public function registerViewsForTest(string $packageRoot): void
        {
            $this->registerPackageViews($packageRoot);
        }
    };

    expect(fn () => $provider->registerViewsForTest($missingPackageRoot))->not->toThrow(Throwable::class);
});
