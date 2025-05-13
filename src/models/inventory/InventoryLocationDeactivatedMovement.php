<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */


namespace craft\commerce\models\inventory;

use craft\commerce\base\InventoryMovement;
use craft\commerce\base\Model;
use craft\commerce\enums\InventoryTransactionType;

/**
 * Inventory Location Deactivate model
 *
 * @since 5.0
 */
class InventoryLocationDeactivatedMovement extends InventoryMovement
{
    /**
     * @return array
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['fromInventoryLocation', 'toInventoryLocation'],
            function($attribute, $params, $validator) {
                if ($this->fromInventoryLocation->id === $this->toInventoryLocation->id) {
                    $validator->addError($this, $attribute, \Craft::t('commerce','The from and to inventory locations must be different.'));
                }
            },
        ];

        $rules[] = [
            ['fromInventoryTransactionType'],
            function($attribute, $params, $validator) {
                if (!in_array($this->fromInventoryTransactionType, InventoryTransactionType::allowedManualMoveTransactionTypes(), true)) {
                    $validator->addError($this, $attribute, 'Can not move between these inventory types.');
                }

                if (!in_array($this->toInventoryTransactionType, InventoryTransactionType::allowedManualMoveTransactionTypes(), true)) {
                    $validator->addError($this, $attribute, 'Can not move between these inventory types.');
                }
            },
        ];

        return $rules;
    }
}
