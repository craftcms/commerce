<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store;

use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Typecast;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Management\Access;
use CraftCms\Commerce\Management\Input;
use CraftCms\Commerce\Store\Data\SiteStore;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

readonly class StoreManagement
{
    public function __construct(private Access $access, private Stores $stores, private StoreSettings $settings)
    {
    }

    public function settings(CraftUser $actor, int $storeId, ?array $attributes = null): array
    {
        $this->access->permission($actor, 'commerce-manageStoreSettings', 'commerce-manageGeneralStoreSettings');
        $this->access->store($actor, $storeId);
        $settings = clone $this->settings->getStoreSettingsById($storeId);
        if ($attributes !== null) {
            Input::validate($attributes, ['type' => 'object', 'properties' => [
                'countries' => ['type' => 'array', 'uniqueItems' => true, 'items' => ['type' => 'string', 'pattern' => '^[A-Z]{2}$']],
                'marketAddressCondition' => ['type' => 'object'],
            ], 'additionalProperties' => false]);
            Typecast::configure($settings, $attributes);
            if (!$this->settings->saveStoreSettings($settings)) {
                throw ValidationException::withMessages($settings->getErrors());
            }
        }
        return ['storeId' => $storeId, 'countries' => $settings->getCountries(), 'marketAddressCondition' => $settings->getMarketAddressCondition()->getConfig(), 'locationAddressId' => $settings->getLocationAddressId()];
    }

    public function topology(CraftUser $actor, int $storeId): array
    {
        $this->access->configuration($actor);
        $this->access->store($actor, $storeId);
        return ['storeId' => $storeId, 'siteIds' => $this->stores->getAllSitesForStore($this->stores->getStoreById($storeId))->pluck('id')->all(), 'inventoryLocationIds' => app(InventoryLocations::class)->getInventoryLocations($storeId)->pluck('id')->values()->all()];
    }

    public function assignSite(CraftUser $actor, int $storeId, int $siteId): array
    {
        $this->access->configuration($actor, true);
        $this->access->store($actor, $storeId);
        if (!Sites::getSiteById($siteId)) {
            throw new InvalidArgumentException('Site not found.');
        }
        $mapping = clone ($this->stores->getAllSiteStores()->firstWhere('siteId', $siteId) ?? new SiteStore(['siteId' => $siteId]));
        $mapping->storeId = $storeId;
        if (!$this->stores->saveSiteStore($mapping)) {
            throw ValidationException::withMessages($mapping->getErrors());
        }
        return $this->topology($actor, $storeId);
    }

    public function inventoryLocations(CraftUser $actor, int $storeId, array $ids): array
    {
        $this->access->configuration($actor, true);
        $this->access->store($actor, $storeId);
        Input::validate(['ids' => $ids], ['type' => 'object', 'properties' => ['ids' => ['type' => 'array', 'uniqueItems' => true, 'minItems' => 1, 'items' => ['type' => 'integer', 'minimum' => 1]]]]);
        $locations = app(InventoryLocations::class);
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
