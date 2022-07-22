<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\cart;

use Craft;
use craft\base\Model;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\models\LineItem;
use craft\helpers\ArrayHelper;
use yii\base\InvalidConfigException;

/**
 * Update Cart Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 */
class UpdateCartForm extends Model
{
    public bool $clearLineItems = false;

    public bool $clearNotices = false;

    public ?int $purchasableId = null;

    public string $purchasableNote = '';
    public int $purchasableQty = 1;
    public array $purchasableOptions = [];

    private array $_purchasables = [];

    private array $_lineItems = [];

    public string $customFieldsNamespace = 'fields';

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [[
            'clearLineItems',
            'clearNotices',
            'purchasableId',
            'note', // Remove later
            'options', // Remove later
            'qty', // Remove later
            'purchasableNote',
            'purchasableOptions', // TODO Commerce 4 should only support key value only #COM-55
            'purchasableQty',
            'purchasables',
        ], 'safe'];

        return $rules;
    }

    /**
     * @param string $note
     * @return void
     * @deprecated in 4.2. Use $purchasableNote instead.
     */
    public function setNote(string $note): void
    {
        $this->purchasableNote = $note;
    }

    /**
     * @param array $options
     * @return void
     * @deprecated in 4.2. Use $purchasableOptions instead.
     */
    public function setOptions(array $options): void
    {
        $this->purchasableOptions = $options;
    }

    /**
     * @param int $qty
     * @return void
     * @deprecated in 4.2. Use $purchasableQty instead.
     */
    public function setQty(int $qty): void
    {
        $this->purchasableQty = $qty;
    }

    /**
     * @param array $purchasables
     * @return void
     * @throws InvalidConfigException
     */
    public function setPurchasables(array $purchasables): void
    {
        $this->_purchasables = [];
        foreach ($purchasables as $purchasable) {
            $purchasableForm = Craft::createObject(AddPurchasableToCartForm::class);
            if (!$purchasableForm->load($purchasable, '') || !$purchasableForm->validate()) {
                // Add errors
                continue;
            }

            if (isset($this->_purchasables[$purchasableForm->getKey()])) {
                $this->_purchasables[$purchasableForm->getKey()]['qty'] += $purchasableForm->qty;
            } else {
                $this->_purchasables[$purchasableForm->getKey()] = $purchasableForm;
            }
        }
    }

    /**
     * @return AddPurchasableToCartForm[]
     */
    public function getPurchasables(): array
    {
        return $this->_purchasables;
    }

    public function setLineItems(array $lineItems): void
    {
        // Sanitize what data we have available
        foreach ($lineItems as &$lineItem) {
            if (isset($lineItem['qty'])) {
                $lineItem['qty'] = (int)$lineItem['qty'];
            }

            if (isset($lineItem['note'])) {
                $lineItem['note'] = (string)$lineItem['note'];
            }

            if (isset($lineItem['options']) && !is_array($lineItem['options'])) {
                $lineItem['options'] = [];
            }

            if (isset($lineItem['remove'])) {
                $lineItem['remove'] = (bool)$lineItem['remove'];
            }
        }
        unset($lineItem);

        $this->_lineItems = $lineItems;
    }

    /**
     * @param LineItem[]|null $existingLineItems
     * @return array
     */
    public function getLineItems(?array $existingLineItems = null): array
    {
        if (empty($this->_lineItems) || $existingLineItems === null) {
            return [];
        }

        $existingLineItemIds = ArrayHelper::getColumn($existingLineItems, 'id');
        if (empty($existingLineItems)) {
            return [];
        }

        $lineItems = [];
        foreach ($this->_lineItems as $lineItemId => $lineItemArray) {
            if (!in_array($lineItemId, $existingLineItemIds, false)) {
                continue;
            }

            $lineItem = ArrayHelper::firstWhere($existingLineItems, 'id', $lineItemId);
            $lineItem->qty = $lineItemArray['qty'] ?? $lineItem->qty;
            $lineItem->note = $lineItemArray['note'] ?? $lineItem->note;
            $lineItem->options = $lineItemArray['options'] ?? $lineItem->options;
        }

        return $lineItems;
    }
}