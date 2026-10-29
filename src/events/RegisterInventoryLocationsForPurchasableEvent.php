<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\Store;
use Illuminate\Support\Collection;
use yii\base\Event;

/**
 * RegisterInventoryLocationsForPurchasableEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.6.5
 *
 * @property Collection $inventoryLocations
 */
class RegisterInventoryLocationsForPurchasableEvent extends Event
{
    /**
     * @var Purchasable The purchasable the inventory locations are being registered for.
     */
    public Purchasable $purchasable;

    /**
     * @var Order|null The order the inventory locations are being registered for.
     */
    public ?Order $order = null;

    /**
     * @var Store The store the inventory locations are being registered for.
     */
    public Store $store;

    /**
     * @var Collection|null The collection of inventory locations for the purchasable, sorted by priority.
     */
    private ?Collection $_inventoryLocations = null;

    /**
     * @var bool Whether trashed inventory locations should be included.
     */
    public bool $withTrashed = false;

    /**
     * @param Collection $inventoryLocations
     * @return void
     */
    public function setInventoryLocations(Collection $inventoryLocations): void
    {
        $this->_inventoryLocations = $inventoryLocations;
    }

    /**
     * @return Collection
     */
    public function getInventoryLocations(): Collection
    {
        return $this->_inventoryLocations ?? collect();
    }
}
