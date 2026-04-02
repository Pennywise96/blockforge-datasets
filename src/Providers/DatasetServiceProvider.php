<?php

namespace Blockforge\Datasets\Providers;

use Blockforge\Cms\Loader\CmsConfigCache;
use Blockforge\Cms\Models\CmsPage;
use Blockforge\Cms\Routing\PageRouteFallbackRegistry;
use Blockforge\Cms\Support\EditorPackageRegistry;
use Blockforge\Cms\ViewHelpers\ViewHelperRegistry;
use Blockforge\Datasets\Routing\CanonicalDatasetDetailPageResolver;
use Blockforge\Datasets\Schemas\DatasetSchema;
use Blockforge\Datasets\Support\DatasetDetailPageService;
use Blockforge\Datasets\ViewHelpers\DatasetCategoriesViewHelper;
use Blockforge\Datasets\ViewHelpers\DatasetContextViewHelper;
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

        if (class_exists(CmsConfigCache::class)) {
            CmsConfigCache::allowClass(DatasetSchema::class);
        }
    }

    public function boot(): void
    {
        $packageRoot = dirname(__DIR__, 2);

        $this->loadMigrationsFrom($packageRoot.'/database/migrations');

        $this->registerPackageViews($packageRoot);

        $this->publishes([
            $packageRoot.'/dist' => public_path('vendor/blockforge/datasets'),
        ], 'blockforge-datasets-assets');

        $viewHelperRegistry = app(ViewHelperRegistry::class);
        $viewHelperRegistry->register('dataset.items', DatasetItemsViewHelper::class);
        $viewHelperRegistry->register('dataset.detail', DatasetDetailViewHelper::class);
        $viewHelperRegistry->register('dataset.categories', DatasetCategoriesViewHelper::class);
        $viewHelperRegistry->register('dataset.context', DatasetContextViewHelper::class);

        Route::middleware('api')
            ->prefix('api')
            ->group($packageRoot.'/routes/api.php');

        CmsPage::saved(function (CmsPage $page): void {
            app(DatasetDetailPageService::class)->syncMappingForPage($page);
        });

        $this->app->booted(function (): void {
            $this->registerEditorIntegration();
        });
    }

    protected function registerPackageViews(string $packageRoot): void
    {
        $viewsPath = $packageRoot.'/resources/views';

        if (! is_dir($viewsPath)) {
            return;
        }

        View::addLocation($viewsPath);
        $this->loadViewsFrom($viewsPath, 'datasets');

        Forte::app()->include($viewsPath.'/**');
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
        $registry->registerBuild(
            'datasets',
            'vendor/blockforge/datasets',
            sourceDistPath: dirname(__DIR__, 2).'/dist',
        );

        if (class_exists(PageRouteFallbackRegistry::class)) {
            $this->app->make(PageRouteFallbackRegistry::class)
                ->register(CanonicalDatasetDetailPageResolver::class);
        }
    }
}
