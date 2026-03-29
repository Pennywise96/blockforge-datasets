<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('registers dataset editor routes with the cms session-auth middleware stack', function (): void {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/cms/datasets/types' && in_array('POST', $route->methods(), true));

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('web')
        ->and($route->gatherMiddleware())->toContain('cms.debug')
        ->and($route->gatherMiddleware())->toContain('cms.site')
        ->and($route->gatherMiddleware())->toContain('auth:web')
        ->and($route->gatherMiddleware())->toContain('throttle:300,1');
});
