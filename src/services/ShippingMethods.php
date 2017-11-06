<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\base\ShippingRuleInterface;
use craft\commerce\elements\Order;
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
 * @property ShippingMethod[] $allShippingMethods
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
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
        // TODO this will happen when shipping methods are refactored. For now just make sure it runs at all on Craft 3.

//        if (null === $this->_shippingMethods) {
//            $shippingMethods = ShippingMethod::populateModels(ShippingMethodRecord::findAll());
//
//            $event = new RegisterComponentTypesEvent([
//                'types' => $shippingMethods
//            ]);
//            $this->trigger(self::EVENT_REGISTER_SHIPPING_METHODS, $event);
//
//            $this->_shippingMethods = $event->types;
//        }
//
//        return $this->_shippingMethods;
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
     * @param string $shippingMethodHandle
     *
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
     * @param int $shippingMethodId
     *
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
     *
     * @return array
     */
    public function getOrderedAvailableShippingMethods($cart): array
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
    public function getAvailableShippingMethods(Order $cart): array
    {
        $availableMethods = [];

        $methods = $this->getAllShippingMethods();

        foreach ($methods as $method) {
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

        return $availableMethods;
    }

    /**
     * @param Order                   $order
     * @param ShippingMethodInterface $method
     *
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
     * @param ShippingMethod $model
     *
     * @return bool
     * @throws Exception
     */
    public function saveShippingMethod(ShippingMethod $model): bool
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
     * @param $shippingMethodId int
     *
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
     *
     * @return void
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
