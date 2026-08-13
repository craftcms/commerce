<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Models;

use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Purchasable\Purchasables;
use CraftCms\Commerce\Services\Stores;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use DateTime;

class CatalogPricing extends Component implements HasStoreInterface
{
    use StoreTrait;

    public ?int $id = null;

    public ?int $purchasableId = null;

    public ?float $price = null;

    public ?int $catalogPricingRuleId = null;

    public ?DateTime $dateFrom = null;

    public ?DateTime $dateTo = null;

    public bool $isPromotionalPrice = false;

    public bool $hasUpdatePending = false;

    public ?string $uid = null;

    private ?\craft\commerce\models\CatalogPricingRule $_catalogPricingRule = null;

    private ?PurchasableInterface $_purchasable = null;

    public function getPurchasable(): ?PurchasableInterface
    {
        if ($this->_purchasable !== null) {
            return $this->_purchasable;
        }

        if ($this->purchasableId === null || $this->storeId === null) {
            return null;
        }

        if (!$store = app(Stores::class)->getStoreById($this->storeId)) {
            throw new \InvalidArgumentException('Invalid store ID: ' . $this->storeId);
        }

        $site = $store->getSites()->first();

        $this->_purchasable = app(Purchasables::class)->getPurchasableById($this->purchasableId, $site->id);

        return $this->_purchasable;
    }

    public function getCatalogPricingRule(): ?\craft\commerce\models\CatalogPricingRule
    {
        if ($this->_catalogPricingRule !== null) {
            return $this->_catalogPricingRule;
        }

        if (!$this->catalogPricingRuleId) {
            return null;
        }

        $this->_catalogPricingRule = app(CatalogPricingRules::class)->getCatalogPricingRuleById($this->catalogPricingRuleId, $this->storeId);

        return $this->_catalogPricingRule;
    }
}
