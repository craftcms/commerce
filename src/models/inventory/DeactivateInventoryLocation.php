<?php

namespace craft\commerce\models\inventory;

use craft\commerce\base\Model;
use craft\commerce\db\Table;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\db\Query;

/**
 * Deactivate (Soft-delete) Inventory Location
 */
class DeactivateInventoryLocation extends Model
{
    /**
     * @var InventoryLocation
     */
    public InventoryLocation $inventoryLocation;

    /**
     * @var InventoryLocation
     */
    public InventoryLocation $destinationInventoryLocation;

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['inventoryLocation', 'destinationInventoryLocation'], 'required'];

        // Ensure the inventory location is not already deactivated
        $rules[] = [
            ['inventoryLocation'],
            function($attribute, $params, $validator) {
                $exists = (new Query())
                    ->select(['id'])
                    ->from([Table::INVENTORYLOCATIONS])
                    ->where(['id' => $this->inventoryLocation->id, 'dateDeleted' => null])
                    ->exists();

                if (!$exists) {
                    $this->addError($attribute, \Craft::t('commerce','Inventory location is already deactivated.'));
                }
            },
        ];

        $rules[] = [
            ['inventoryLocation'],
            function($attribute, $params, $validator) {
                // Look through all the stores and see if they only have 1 location and it's the one we are deactivating
                $stores = Plugin::getInstance()->getStores()->getAllStores();
                foreach($stores as $store) {
                    $locations = $store->getInventoryLocations();
                    if ($locations->count() == 1 && $locations->contains('id', $this->inventoryLocation->id)) {
                        $this->addError($attribute, \Craft::t('commerce','This is the last location for the {store} store.', ['store' => $store->getName()]));
                    }
                }
            },
        ];

        $rules[] = [
            ['inventoryLocation'],
            function($attribute, $params, $validator) {
                if ($this->hasOutStandingCommittedStock()) {
                    $this->addError($attribute, \Craft::t('commerce','Inventory location has committed stock, the order(s) must first be fulfilled.'));
                }
            },
        ];

        $rules[] = [
            ['inventoryLocation'],
            function($attribute, $params, $validator) {
                if ($this->hasOutStandingIncomingStock()) {
                    $this->addError($attribute, \Craft::t('commerce','Inventory location has incoming stock, the transfer(s) must first be completed.'));
                }
            },
        ];

        return $rules;
    }

    public function hasOutStandingCommittedStock(): bool
    {
        $committedTotal = Plugin::getInstance()->getInventory()->getInventoryLocationLevels($this->inventoryLocation)
            ->sum('committedTotal');

        return $committedTotal > 0;
    }

    public function hasOutStandingIncomingStock(): bool
    {
        $incomingTotal = Plugin::getInstance()->getInventory()->getInventoryLocationLevels($this->inventoryLocation)
            ->sum('incomingTotal');

        return $incomingTotal > 0;
    }

    public function getMigrationInformation(): array
    {
        // Get inventoryLevels at the destination location

        $inventoryLevels = Plugin::getInstance()->getInventory()->getInventoryLevelQuery()
            ->where(['locationId' => $this->destinationInventoryLocation->id])
            ->all();
    }
}
