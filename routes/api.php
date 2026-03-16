<?php

use Blockforge\Datasets\Http\Controllers\Api\DatasetCategoryController;
use Blockforge\Datasets\Http\Controllers\Api\DatasetController;
use Blockforge\Datasets\Http\Controllers\Api\DatasetTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('cms')->middleware('auth')->group(function () {
    // Dataset types
    Route::get('datasets/types', [DatasetTypeController::class, 'index']);
    Route::post('datasets/types', [DatasetTypeController::class, 'store']);
    Route::put('datasets/types/{datasetType}', [DatasetTypeController::class, 'update']);
    Route::delete('datasets/types/{datasetType}', [DatasetTypeController::class, 'destroy']);

    // Dataset categories (scoped to type)
    Route::get('datasets/types/{datasetType}/categories', [DatasetCategoryController::class, 'index']);
    Route::post('datasets/types/{datasetType}/categories', [DatasetCategoryController::class, 'store']);
    Route::put('datasets/categories/{datasetCategory}', [DatasetCategoryController::class, 'update']);
    Route::delete('datasets/categories/{datasetCategory}', [DatasetCategoryController::class, 'destroy']);

    // Datasets (entries)
    Route::get('datasets', [DatasetController::class, 'index']);
    Route::post('datasets', [DatasetController::class, 'store']);
    Route::get('datasets/{dataset}', [DatasetController::class, 'show']);
    Route::put('datasets/{dataset}', [DatasetController::class, 'update']);
    Route::delete('datasets/{dataset}', [DatasetController::class, 'destroy']);
    Route::put('datasets/{dataset}/translations/{locale}', [DatasetController::class, 'updateTranslation']);
    Route::put('datasets/{dataset}/categories', [DatasetController::class, 'syncCategories']);
});
