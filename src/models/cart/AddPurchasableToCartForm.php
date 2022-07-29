<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\cart;

use craft\commerce\base\CartForm;
use craft\commerce\helpers\LineItem;
use craft\commerce\Plugin;

/**
 * Add Purchasable To Cart Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 *
 * @property-read string $key
 */
class AddPurchasableToCartForm extends CartForm
{
    public ?int $id = null;
    public string $note = '';
    public array $options = [];
    public int $qty = 1;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [[
            'id',
            'qty',
            'note',
            'options',
        ], 'safe'];
        $rules[] = [['id'], 'validatePurchasable'];
        $rules[] = [['qty'], 'validateQty'];

        return $rules;
    }

    public function validatePurchasable()
    {
        if (!Plugin::getInstance()->getPurchasables()->getPurchasableById($this->id)) {
            $this->addError('id', Craft::t('commerce', 'Unable to retrieve purchasable.'));
        }
    }

    public function validateQty(): void
    {
        if ($this->qty == 0) {
            return;
        }

        $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($this->id);
        if (!$purchasable) {
            $this->addError('id', Craft::t('commerce', 'Unable to retrieve purchasable.'));
        }
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->id . '-' . LineItem::generateOptionsSignature($this->options);
    }

    /**
     * @inheritdoc
     */
    public function apply(): bool
    {
        if (!parent::apply()) {
            return false;
        }

        if ($this->qty === 0) {
            return true;
        }

        $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->getOrder(), $this->id, $this->options);

        // New line items already have a qty of one.
        if ($lineItem->id) {
            $lineItem->qty += $this->qty;
        } else {
            $lineItem->qty = $this->qty;
        }

        $lineItem->note = $this->note;

        $this->getOrder()->addLineItem($lineItem);

        return true;
    }
}
