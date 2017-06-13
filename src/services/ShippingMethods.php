<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\base\ShippingRuleInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use yii\base\Component;

/**
 * Shipping method service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class ShippingMethods extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent The event that is triggered when registering additional shipping methods.
     */
    const EVENT_REGISTER_SHIPPING_METHODS = 'registerShippingMethods';

    // Properties
    // =========================================================================
    
    /**
     * @var bool
     */
    private $_shippingMethods;

    // Public Methods
    // =========================================================================

    /**
     * @param int $id
     *
     * @return ShippingMethod|null
     */
    public function getShippingMethodById($id)
    {
        $result = ShippingMethodRecord::findOne($id);

        if ($result) {
            return new ShippingMethod($result);
        }

        return null;
    }

    /**
     * @param string $handle
     *
     * @return ShippingMethodInterface|null
     */
    public function getShippingMethodByHandle($handle)
    {
        $methods = $this->getAllShippingMethods();

        foreach ($methods as $method) {
            if ($method->getHandle() == $handle) {
                return $method;
            }
        }

        return null;
    }

    /**
     * Returns the Commerce managed and 3rd party shipping methods
     *
     * @return ShippingMethod[]
     */
    public function getAllShippingMethods()
    {
        // TODO this will change when shupping methods are refactored.
        if (null === $this->_shippingMethods) {
            $shippingMethods = ShippingMethod::populateModels(ShippingMethodRecord::findAll());

            $event = new RegisterComponentTypesEvent([
                'types' => $shippingMethods
            ]);
            $this->trigger(self::EVENT_REGISTER_SHIPPING_METHODS, $event);

            $this->_shippingMethods = $event->types;
        }

        return $this->_shippingMethods;
    }

    /**
     * @return bool
     */
    public function ShippingMethodExists()
    {
        return ShippingMethodRecord::find()->exists();
    }

    /**
     * @param Order $cart
     *
     * @return array
     */
    public function getOrderedAvailableShippingMethods($cart)
    {
        $availableMethods = $this->getAvailableShippingMethods($cart);

        uasort($availableMethods, function($a, $b) {
            return $a['amount'] - $b['amount'];
        });

        return $availableMethods;
    }

    /**
     * @param Order $cart
     *
     * @return array
     */
    public function getAvailableShippingMethods(Order $cart)
    {
        $availableMethods = [];

        $methods = $this->getAllShippingMethods();

        foreach ($methods as $method) {
            if ($method->getIsEnabled() && $rule = $this->getMatchingShippingRule($cart, $method)) {
                $amount = $rule->getBaseRate();

                foreach ($cart->lineItems as $item) {
                    if ($item->purchasable && !$item->purchasable->hasFreeShipping()) {
                        $percentageRate = $rule->getPercentageRate($item->shippingCategoryId);
                        $perItemRate = $rule->getPerItemRate($item->shippingCategoryId);
                        $weightRate = $rule->getWeightRate($item->shippingCategoryId);

                        $percentageAmount = $item->getSubtotal() * $percentageRate;
                        $perItemAmount = $item->qty * $perItemRate;
                        $weightAmount = ($item->weight * $item->qty) * $weightRate;

                        $amount += ($percentageAmount + $perItemAmount + $weightAmount);
                    }
                }

                $amount = max($amount, $rule->getMinRate() * 1);

                if ($rule->getMaxRate() * 1) {
                    $amount = min($amount, $rule->getMaxRate() * 1);
                }

                $availableMethods[$method->getHandle()] = [
                    'name' => $method->getName(),
                    'description' => $rule->getDescription(),
                    'amount' => $amount,
                    'handle' => $method->getHandle(),
                    'type' => $method->getType(),
                    'method' => $method
                ];
            }
        }

        return $availableMethods;
    }

    /**
     * @param Order                   $order
     * @param ShippingMethodInterface $method
     *
     * @return bool|ShippingMethod
     */
    public function getMatchingShippingRule(
        Order $order,
        ShippingMethod $method
    ) {
        foreach ($method->getShippingRules() as $rule) {
            /** @var ShippingRuleInterface $rule */
            if ($rule->matchOrder($order)) {
                return $rule;
            }
        }

        return false;
    }

    /**
     * @param ShippingMethod $model
     *
     * @return bool
     * @throws \Exception
     */
    public function saveShippingMethod(ShippingMethod $model)
    {
        if ($model->id) {
            $record = ShippingMethodRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No shipping method exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new ShippingMethodRecord();
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->enabled = $model->enabled;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        }

        return false;
    }


    /**
     * @param $model
     *
     * @return bool
     */
    public function delete($model)
    {
        // Delete all rules first.
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {

            $rules = Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($model->id);
            foreach ($rules as $rule) {
                Plugin::getInstance()->getShippingRules()->deleteShippingRuleById($rule->id);
            }

            $record = ShippingMethodRecord::findOne($model->id);
            $record->delete();

            $transaction->commit();

            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();

            return false;
        }
    }
}
