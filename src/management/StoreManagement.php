<?php

declare(strict_types=1);

namespace craft\commerce\management;

use Craft;
use craft\commerce\models\SiteStore;
use craft\commerce\Plugin;
use craft\commerce\services\Stores;
use craft\commerce\services\StoreSettings;
use craft\elements\User;
use InvalidArgumentException;

readonly class StoreManagement
{
    private Stores $stores;
    private StoreSettings $settings;

    public function __construct(private Access $access)
    {
        $this->stores = Plugin::getInstance()->getStores();
        $this->settings = Plugin::getInstance()->getStoreSettings();
    }

    public function settings(User $actor, int $storeId, ?array $attributes = null): array
    {
        $this->access->permission($actor, 'commerce-manageStoreSettings', 'commerce-manageGeneralStoreSettings');
        $this->access->store($actor, $storeId);
        $settings = clone $this->settings->getStoreSettingsById($storeId);
        if ($attributes !== null) {
            Input::validate($attributes, ['type' => 'object', 'properties' => [
                'countries' => ['type' => 'array', 'uniqueItems' => true, 'items' => ['type' => 'string', 'pattern' => '^[A-Z]{2}$']],
                'marketAddressCondition' => ['type' => 'object'],
            ], 'additionalProperties' => false]);
            Craft::configure($settings, $attributes);
            if (!$this->settings->saveStoreSettings($settings)) {
                throw new InvalidArgumentException(implode("\n", $settings->getErrorSummary(true)));
            }
        }
        return ['storeId' => $storeId, 'countries' => $settings->getCountries(), 'marketAddressCondition' => $settings->getMarketAddressCondition()->getConfig(), 'locationAddressId' => $settings->getLocationAddressId()];
    }

    public function topology(User $actor, int $storeId): array
    {
        $this->access->configuration($actor);
        $this->access->store($actor, $storeId);
        return ['storeId' => $storeId, 'siteIds' => $this->stores->getAllSitesForStore($this->stores->getStoreById($storeId))->pluck('id')->all(), 'inventoryLocationIds' => Plugin::getInstance()->getInventoryLocations()->getInventoryLocations($storeId)->pluck('id')->values()->all()];
    }

    public function assignSite(User $actor, int $storeId, int $siteId): array
    {
        $this->access->configuration($actor, true);
        $this->access->store($actor, $storeId);
        if (!Craft::$app->getSites()->getSiteById($siteId)) {
            throw new InvalidArgumentException('Site not found.');
        }
        $mapping = clone ($this->stores->getAllSiteStores()->firstWhere('siteId', $siteId) ?? new SiteStore(['siteId' => $siteId]));
        $mapping->storeId = $storeId;
        if (!$this->stores->saveSiteStore($mapping)) {
            throw new InvalidArgumentException(implode("\n", $mapping->getErrorSummary(true)));
        }
        return $this->topology($actor, $storeId);
    }

    public function inventoryLocations(User $actor, int $storeId, array $ids): array
    {
        $this->access->configuration($actor, true);
        $this->access->store($actor, $storeId);
        Input::validate(['ids' => $ids], ['type' => 'object', 'properties' => ['ids' => ['type' => 'array', 'uniqueItems' => true, 'minItems' => 1, 'items' => ['type' => 'integer', 'minimum' => 1]]]]);
        $locations = Plugin::getInstance()->getInventoryLocations();
        foreach ($ids as $id) {
            if (!$locations->getInventoryLocationById($id)) {
                throw new InvalidArgumentException('Inventory location not found.');
            }
        }
        if (!$locations->saveStoreInventoryLocations($this->stores->getStoreById($storeId), $ids)) {
            throw new InvalidArgumentException('Inventory locations could not be saved.');
        }
        return $this->topology($actor, $storeId);
    }
}
