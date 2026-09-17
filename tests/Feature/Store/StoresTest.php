<?php

declare(strict_types=1);

use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Site\Events\SiteSaved;
use CraftCms\Commerce\Store\Models\SiteStore as SiteStoreRecord;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\StoresFixture;
use Illuminate\Support\Collection;

/**
 * Runs $callback with ProjectConfig::isApplyingExternalChanges forced to $isApplying, restoring
 * its original value afterwards. There is no public setter for this asymmetric-visibility
 * property, so Reflection is required.
 */
function withApplyingExternalChanges(bool $isApplying, callable $callback): void
{
    $projectConfig = app(ProjectConfig::class);
    $prop = new ReflectionProperty(ProjectConfig::class, 'isApplyingExternalChanges');
    $prop->setAccessible(true);
    $originalValue = $prop->getValue($projectConfig);

    $prop->setValue($projectConfig, $isApplying);

    try {
        $callback();
    } finally {
        $prop->setValue($projectConfig, $originalValue);
    }
}

test('getAllStores returns every store, with exactly one primary', function() {
    $fixture = StoresFixture::seed();
    $stores = app(Stores::class)->getAllStores();

    expect($stores)->toBeInstanceOf(Collection::class)
        ->and($stores)->toHaveCount(3)
        ->and($stores->firstWhere('primary', true)->handle)->toBe('primary')
        ->and($stores->where('primary', false)->all())->toHaveCount(2);
});

test('getStoreBySiteId returns the store mapped to a site, and null for an unmapped site', function() {
    $fixture = StoresFixture::seed();
    $stores = app(Stores::class);

    expect($stores->getStoreBySiteId($fixture->usSite->id)?->handle)->toBe('primary')
        ->and($stores->getStoreBySiteId($fixture->euSite->id)?->handle)->toBe('euStore')
        ->and($stores->getStoreBySiteId($fixture->ukSite->id)?->handle)->toBe('ukStore')
        ->and($stores->getStoreBySiteId(999999))->toBeNull();
});

test('afterSaveCraftSiteHandler skips creating a mapping while applying external changes', function() {
    // While a project config apply (e.g. `craft up`) is in progress, the incoming sitestores
    // config is responsible for creating the mapping via handleChangedSiteStore(). If
    // afterSaveCraftSiteHandler() also created one here, it would assign the wrong (primary)
    // store and trigger an unwanted project config write.
    $fixture = StoresFixture::seed();
    $site = $fixture->ukSite;

    // Remove the fixture's existing mapping so the handler would normally recreate one.
    SiteStoreRecord::where('siteId', $site->id)->delete();

    withApplyingExternalChanges(true, function() use ($site) {
        app(Stores::class)->afterSaveCraftSiteHandler(new SiteSaved(site: $site));
    });

    expect(SiteStoreRecord::where('siteId', $site->id)->first())->toBeNull();
});

test('afterSaveCraftSiteHandler creates a mapping to the primary store outside of an apply', function() {
    $fixture = StoresFixture::seed();
    $site = $fixture->ukSite;

    SiteStoreRecord::where('siteId', $site->id)->delete();

    withApplyingExternalChanges(false, function() use ($site) {
        app(Stores::class)->afterSaveCraftSiteHandler(new SiteSaved(site: $site));
    });

    $siteStore = SiteStoreRecord::where('siteId', $site->id)->first();

    expect($siteStore)->not->toBeNull()
        ->and($siteStore->storeId)->toBe($fixture->primaryStore->id);
});
