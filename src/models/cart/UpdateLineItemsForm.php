<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\cart;

use Craft;
use craft\commerce\base\CartForm;

/**
 * Add Purchasable To Cart Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 */
class UpdateLineItemsForm extends CartForm
{
    /**
     * @var UpdateLineItemForm[]
     */
    private array $_lineItems = [];

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['lineItems'], 'safe'];
        $rules[] = [['lineItems'], 'validateLineItemForms'];

        return $rules;
    }

    /**
     * @return void
     */
    public function validateLineItemForms(): void
    {
        foreach ($this->getLineItems() as $key => $updateLineItemForm) {
            if (!$updateLineItemForm->validate()) {
                $this->addModelErrors($updateLineItemForm, sprintf('lineItems[%s]', $key));
            }
        }
    }

    public function setLineItems(array $lineItems): void
    {
        $this->_lineItems = [];
        foreach ($lineItems as $id => $lineItem) {
            $updateLineItemForm = Craft::createObject(UpdateLineItemForm::class);
            if (!$updateLineItemForm->load(array_merge($lineItem, compact('id')), '')) {
                $this->addModelErrors($updateLineItemForm, sprintf('lineItems[%s]', $updateLineItemForm->id));
                continue;
            }

            $this->_lineItems[$updateLineItemForm->id] = $updateLineItemForm;
        }
    }

    /**
     * @return UpdateLineItemForm[]
     */
    public function getLineItems(): array
    {
        return $this->_lineItems;
    }

    /**
     * @inerhitdoc
     */
    public function apply(): bool
    {
        if (!parent::apply()) {
            return false;
        }

        $return = true;
        foreach ($this->getLineItems() as $key => $updateLineItemForm) {
            if (!$updateLineItemForm->apply()) {
                $return = false;
                $this->addModelErrors($updateLineItemForm, sprintf('lineItems[%s]', $key));
            }
        }

        return $return;
    }
}
