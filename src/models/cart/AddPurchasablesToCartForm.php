<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\cart;

use Craft;
use craft\commerce\base\CartForm;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Add Purchasable To Cart Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 */
class AddPurchasablesToCartForm extends CartForm
{
    /**
     * @var AddPurchasableToCartForm[]
     */
    private array $_purchasables = [];

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['purchasables'], 'safe'];
        $rules[] = [['purchasables'], 'validatePurchasableForms'];

        return $rules;
    }

    /**
     * @return void
     */
    public function validatePurchasableForms(): void
    {
        foreach ($this->getPurchasables() as $index => $addPurchasableToCartForm) {
            if (!$addPurchasableToCartForm->validate()) {
                $this->addModelErrors($addPurchasableToCartForm, sprintf('purchasables[%s]', $index));
            }
        }
    }

    /**
     * @param array $purchasables
     * @return void
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function setPurchasables(array $purchasables): void
    {
        $this->_purchasables = [];
        foreach ($purchasables as $index => $purchasable) {
            $purchasableForm = Craft::createObject([
                'class' => AddPurchasableToCartForm::class,
                'order' => $this->getOrder(),
            ]);
            if (!$purchasableForm->load($purchasable, '')) {
                $this->addModelErrors($purchasableForm, sprintf('purchasables[%s]', $index));
                continue;
            }

            // Normalize to avoid adding duplicates
            /** @var AddPurchasableToCartForm|null $existingPurchasableForm */
            $existingPurchasableForm = ArrayHelper::firstWhere($this->_purchasables, static function($pf) use ($purchasableForm) {
                return $pf->getKey() === $purchasableForm->getKey();
            });
            if ($existingPurchasableForm) {
                $purchasableForm += $existingPurchasableForm->qty;
            }

            $this->_purchasables[] = $purchasableForm;
        }
    }

    /**
     * @return AddPurchasableToCartForm[]
     */
    public function getPurchasables(): array
    {
        return $this->_purchasables;
    }

    /**
     * @inheritdoc
     */
    public function apply(): bool
    {
        if (!parent::apply()) {
            return false;
        }

        $return = true;
        foreach ($this->getPurchasables() as $index => $addPurchasableToCartForm) {
            // Ignore zero value qty for multi-add forms https://github.com/craftcms/commerce/issues/330#issuecomment-384533139
            if ($addPurchasableToCartForm->qty === 0) {
                continue;
            }

            if (!$addPurchasableToCartForm->apply()) {
                $return = false;
                $this->addModelErrors($addPurchasableToCartForm, sprintf('purchasables[%s]', $index));
            }
        }

        return $return;
    }
}
