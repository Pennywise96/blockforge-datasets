<?php

use App\Models\User;
use Blockforge\Cms\Http\Middleware\ResolveCmsSite;
use Blockforge\Cms\Models\CmsSite;
use Blockforge\Cms\Models\CmsSiteLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ResolveCmsSite::class);
    $this->actingAs(User::factory()->create());
});

function editorContextSite(string $handle = 'datasets-editor-integration'): CmsSite
{
    $site = CmsSite::query()->firstOrCreate(
        ['handle' => $handle],
        ['name' => 'Datasets Editor Integration', 'active' => true],
    );

    app()->instance(CmsSite::class, $site);

    return $site;
}

function editorContextLocale(CmsSite $site, string $locale = 'en'): CmsSiteLocale
{
    return CmsSiteLocale::query()->firstOrCreate(
        ['site_id' => $site->id, 'locale' => $locale],
        [
            'label' => strtoupper($locale),
            'is_default' => true,
            'is_prefixed' => false,
            'translation_mode' => 'fallback',
            'sort_order' => 0,
        ],
    );
}

it('exposes the datasets editor integration through the cms context api', function (): void {
    $site = editorContextSite();
    editorContextLocale($site);

    $this->getJson('/api/cms/context?path=/')
        ->assertOk()
        ->assertJsonPath('features.datasets', true)
        ->assertJsonPath('integrations.datasets.provider', 'Blockforge Datasets')
        ->assertJsonPath('integrations.datasets.package', 'blockforge/datasets')
        ->assertJsonPath('integrations.datasets.connected', true);
});
