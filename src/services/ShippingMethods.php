<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\base\ShippingRuleInterface;
use craft\commerce\elements\Order;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Shipping method service.
 *
 * @property ShippingMethod[] $allShippingMethods the Commerce managed and 3rd party shipping methods
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingMethods extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterShippingMethods The event that is triggered when registering additional shipping methods for the cart.
     */
    const EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS = 'registerAvailableShippingMethods';

    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllShippingMethods = false;

    /**
     * @var ShippingMethod[]
     */
    private $_shippingMethodsById = [];

    /**
     * @var ShippingMethod[]
     */
    private $_shippingMethodsByHandle = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns the Commerce managed and 3rd party shipping methods
     *
     * @return ShippingMethod[]
     */
    public function getAllShippingMethods(): array
    {
        if (!$this->_fetchedAllShippingMethods) {
            $results = $this->_createShippingMethodQuery()->all();

            foreach ($results as $result) {
                $this->_memoizeShippingMethod(new ShippingMethod($result));
            }

            $this->_fetchedAllShippingMethods = true;
        }

        return $this->_shippingMethodsById;
    }

    /**
     * Get a shipping method by its handle.
     *
     * @param string $shippingMethodHandle
     * @return ShippingMethod|null
     */
    public function getShippingMethodByHandle(string $shippingMethodHandle)
    {
        if (isset($this->_shippingMethodsByHandle[$shippingMethodHandle])) {
            return $this->_shippingMethodsByHandle[$shippingMethodHandle];
        }

        if ($this->_fetchedAllShippingMethods) {
            return null;
        }

        $result = $this->_createShippingMethodQuery()
            ->where(['handle' => $shippingMethodHandle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeShippingMethod(new ShippingMethod($result));

        return $this->_shippingMethodsByHandle[$shippingMethodHandle];
    }

    /**
     * Get a shipping method by its ID.
     *
     * @param int $shippingMethodId
     * @return ShippingMethod|null
     */
    public function getShippingMethodById(int $shippingMethodId)
    {
        if (isset($this->_shippingMethodsById[$shippingMethodId])) {
            return $this->_shippingMethodsById[$shippingMethodId];
        }

        if ($this->_fetchedAllShippingMethods) {
            return null;
        }

        $result = $this->_createShippingMethodQuery()
            ->where(['id' => $shippingMethodId])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeShippingMethod(new ShippingMethod($result));

        return $this->_shippingMethodsById[$shippingMethodId];
    }

    /**
     * @param Order $cart
     * @return array
     * @deprecated as of 2.0
     */
    public function getOrderedAvailableShippingMethods(Order $cart): array
    {
        Craft::$app->getDeprecator()->log('ShippingMethods::getOrderedAvailableShippingMethods', 'ShippingMethods::getOrderedAvailableShippingMethods us has been deprecated. Use ShippingMethods::getAvailableShippingMethods instead. Shipping Methods are now always returned in price order.');

        return $this->getAvailableShippingMethods($cart);
    }

    /**
     * Get all available shipping methods.
     *
     * @param Order $cart
     * @return array
     */
    public function getAvailableShippingMethods(Order $cart): array
    {
        $availableMethods = [];

        $methods = $this->getAllShippingMethods();

        $event = new RegisterAvailableShippingMethodsEvent([
            'shippingMethods' => $methods,
            'order' => $cart
        ]);

        if ($this->hasEventHandlers(self::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS)) {
            $this->trigger(self::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, $event);
        }

        foreach ($event->shippingMethods as $method) {
            /**
             * @var ShippingRule $rule
             */
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

                $amount = max($amount, $rule->getMinRate());

                if ($rule->getMaxRate()) {
                    $amount = min($amount, $rule->getMaxRate());
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

        uasort($availableMethods, function($a, $b) {
            return $a['amount'] - $b['amount'];
        });

        return $availableMethods;
    }

    /**
     * Get a matching shipping rule for Order and shipping method.
     *
     * @param Order $order
     * @param ShippingMethodInterface $method
     * @return bool|ShippingRuleInterface
     */
    public function getMatchingShippingRule(Order $order, $method)
    {
        foreach ($method->getShippingRules() as $rule) {
            /** @var ShippingRuleInterface $rule */
            if ($rule->matchOrder($order)) {
                return $rule;
            }
        }

        return false;
    }

    /**
     * Save a shipping method.
     *
     * @param ShippingMethod $model
     * @param bool $runValidation should we validate this method before saving.
     * @return bool
     * @throws Exception
     */
    public function saveShippingMethod(ShippingMethod $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = ShippingMethodRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No shipping method exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new ShippingMethodRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Shipping method not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->enabled = $model->enabled;

        $record->validate();
        $model->addErrors($record->getErrors());

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        return true;
    }

    /**
     * Delete a shipping method by its ID.
     *
     * @param $shippingMethodId int
     * @return bool
     */
    public function deleteShippingMethodById($shippingMethodId): bool
    {
        // Delete all rules first.
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $rules = Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($shippingMethodId);

            foreach ($rules as $rule) {
                Plugin::getInstance()->getShippingRules()->deleteShippingRuleById($rule->id);
            }

            $record = ShippingMethodRecord::findOne($shippingMethodId);
            $record->delete();

            $transaction->commit();

            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();

            return false;
        }
    }

    // Private methods
    // =========================================================================

    /**
     * Memoize a shipping method model by its ID and handle.
     *
     * @param ShippingMethod $shippingMethod
     */
    private function _memoizeShippingMethod(ShippingMethod $shippingMethod)
    {
        $this->_shippingMethodsById[$shippingMethod->id] = $shippingMethod;
        $this->_shippingMethodsByHandle[$shippingMethod->handle] = $shippingMethod;
    }

    /**
     * Returns a Query object prepped for retrieving shipping methods.
     *
     * @return Query
     */
    private function _createShippingMethodQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'enabled',
            ])
            ->from(['{{%commerce_shippingmethods}}']);
    }
}
