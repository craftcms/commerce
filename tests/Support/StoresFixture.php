<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Store\Data\SiteStore;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use RuntimeException;

/**
 * Builds a three-store, three-site layout (primary/US, euStore/NL, ukStore/GB) for multi-store
 * `Stores` service tests.
 */
class StoresFixture
{
    public Store $primaryStore;

    public Store $euStore;

    public Store $ukStore;

    public Site $usSite;

    public Site $euSite;

    public Site $ukSite;

    public static function seed(): self
    {
        $fixture = new self();
        $fixture->build();

        return $fixture;
    }

    private function build(): void
    {
        $stores = app(Stores::class);

        $this->primaryStore = $stores->getPrimaryStore();
        $this->usSite = Sites::getCurrentSite();

        $this->euSite = $this->createSite('EU Site', 'euSite', 'nl');
        $this->euStore = $this->createStore('EU Store', 'euStore');
        $this->assignSiteToStore($this->euSite, $this->euStore);

        $this->ukSite = $this->createSite('UK Site', 'ukSite', 'en-GB');
        $this->ukStore = $this->createStore('UK Store', 'ukStore');
        $this->assignSiteToStore($this->ukSite, $this->ukStore);
    }

    private function createSite(string $name, string $handle, string $language): Site
    {
        $site = new Site([
            'name' => $name,
            'handle' => $handle,
            'language' => $language,
            'baseUrl' => 'https://' . $handle . '.test',
            'hasUrls' => true,
            'groupId' => $this->usSite->groupId,
        ]);

        if (!Sites::saveSite($site)) {
            throw new RuntimeException('Could not save site: ' . json_encode($site->errors()->all()));
        }

        return $site;
    }

    private function createStore(string $name, string $handle): Store
    {
        $store = new Store([
            'name' => $name,
            'handle' => $handle,
            'primary' => false,
        ]);

        if (!app(Stores::class)->saveStore($store)) {
            throw new RuntimeException('Could not save store: ' . json_encode($store->errors()->all()));
        }

        return app(Stores::class)->getStoreByHandle($handle)
            ?? throw new RuntimeException('Store not found after save: ' . $handle);
    }

    private function assignSiteToStore(Site $site, Store $store): void
    {
        $stores = app(Stores::class);

        // Creating the site above triggers Commerce's SiteSaved listener, which maps the new
        // site to the primary store by default (one row per site) — fetch and repoint that row.
        $siteStore = $stores->getAllSiteStores()->firstWhere('siteId', $site->id);

        if (!$siteStore) {
            $siteStore = new SiteStore(['siteId' => $site->id]);
        }

        $siteStore->storeId = $store->id;

        if (!$stores->saveSiteStore($siteStore)) {
            throw new RuntimeException('Could not save site store mapping: ' . json_encode($siteStore->errors()->all()));
        }
    }
}
