<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\errors\ElementNotFoundException;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Cart Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 *
 * @property Order|null $cart
 */
abstract class CartForm extends OrderForm
{
    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = ['order', 'validateIsCart'];

        return $rules;
    }

    /**
     * @param string $attribute
     * @return void
     */
    public function validateIsCart(string $attribute): void
    {
        if ($this->$attribute && $this->$attribute->isCompleted) {
            $this->addError($attribute, Craft::t('commerce', 'Cart must not be completed.'));
        }
    }

    /**
     * @return Order|null
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function getOrder(): Order
    {
        return parent::getOrder() ?? Plugin::getInstance()->getCarts()->getCart();
    }

    /**
     * Alias for `getOrder()` for simpler templating
     *
     * @return Order
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function getCart(): Order
    {
        return $this->getOrder();
    }

    /**
     * @inheritdoc
     */
    public function apply(): bool
    {
        if (!parent::apply()) {
            return false;
        }

        return true;
    }
}
