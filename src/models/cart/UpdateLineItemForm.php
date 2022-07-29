<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\cart;

use Craft;
use craft\commerce\base\CartForm;
use craft\helpers\ArrayHelper;

/**
 * Add Purchasable To Cart Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 */
class UpdateLineItemForm extends CartForm
{
    public ?int $id = null;
    public string $note = '';
    public array $options = [];
    public mixed $qty = 1;
    public bool $remove = false;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [[
            'id',
            'qty',
            'note',
            'options',
            'remove',
        ], 'safe'];
        $rules[] = [['id', 'qty'], 'integer'];
        $rules[] = [['id'], 'validateLineItemExists'];
        $rules[] = [['qty'], 'validateQty'];

        return $rules;
    }

    public function validateLineItemExists(string $attribute): void
    {
        $lineItem = ArrayHelper::firstWhere($this->getOrder()->getLineItems(), 'id', $this->id);
        if (!$lineItem) {
            $this->addError($attribute, Craft::t('commerce', 'Line item does not exist.'));
        }
    }

    public function validateQty(string $attribute): void
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(): bool
    {
        if (!parent::apply()) {
            return false;
        }

        $lineItem = ArrayHelper::firstWhere($this->getOrder()->getLineItems(), 'id', $this->id);
        $lineItem->qty = $this->qty;
        $lineItem->note = $this->note;
        $lineItem->setOptions($this->options);

        if (($lineItem->qty !== null && $lineItem->qty == 0) || $this->remove) {
            $this->getOrder()->removeLineItem($lineItem);
        } else {
            $this->getOrder()->addLineItem($lineItem);
        }

        return true;
    }
}
