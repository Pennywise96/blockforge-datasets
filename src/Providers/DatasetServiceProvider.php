<?php

namespace Blockforge\Datasets\Providers;

use Blockforge\Cms\Support\EditorPackageRegistry;
use Blockforge\Cms\ViewHelpers\ViewHelperRegistry;
use Blockforge\Datasets\ViewHelpers\DatasetDetailViewHelper;
use Blockforge\Datasets\ViewHelpers\DatasetItemsViewHelper;
use Forte\Facades\Forte;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class DatasetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config(['blockforge.features.datasets' => true]);
    }

    public function boot(): void
    {
        $packageRoot = dirname(__DIR__, 2);

        $this->loadMigrationsFrom($packageRoot.'/database/migrations');

        View::addLocation($packageRoot.'/resources/views');
        $this->loadViewsFrom($packageRoot.'/resources/views', 'datasets');

        Forte::app()->include($packageRoot.'/resources/views/**');

        $viewHelperRegistry = app(ViewHelperRegistry::class);
        $viewHelperRegistry->register('dataset.items', DatasetItemsViewHelper::class);
        $viewHelperRegistry->register('dataset.detail', DatasetDetailViewHelper::class);

        Route::middleware('api')
            ->prefix('api')
            ->group($packageRoot.'/routes/api.php');

        $this->app->booted(function (): void {
            $this->registerEditorIntegration();
        });
    }

    private function registerEditorIntegration(): void
    {
        if (! class_exists(EditorPackageRegistry::class)) {
            return;
        }

        $registry = $this->app->make(EditorPackageRegistry::class);

        $registry->registerIntegration('datasets', [
            'provider' => 'Blockforge Datasets',
            'package' => 'blockforge/datasets',
            'connected' => true,
        ]);
    }
}
