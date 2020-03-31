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
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use craft\commerce\records\ShippingRule as ShippingRuleRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Shipping method service.
 *
 * @property ShippingMethod $liteShippingMethod
 * @property ShippingMethod[] $allShippingMethods the Commerce managed and 3rd party shipping methods
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingMethods extends Component
{
    /**
     * @event RegisterShippingMethods The event that is triggered for registration of additional shipping methods.
     *
     * This example adds an instance of `MyShippingMethod` to the event object’s `shippingMethods` array:
     *
     * ```php
     * use craft\events\RegisterComponentTypesEvent;
     * use craft\commerce\services\ShippingMethods;
     * use yii\base\Event;
     *
     * Event::on(
     *     ShippingMethods::class,
     *     ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS,
     *     function(RegisterComponentTypesEvent $event) {
     *         $event->shippingMethods[] = MyShippingMethod::class;
     *     }
     * );
     * ```
     */
    const EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS = 'registerAvailableShippingMethods';


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
                $shippingMethod = new ShippingMethod($result);
                $shippingMethod->typecastAttributes();
                $this->_memoizeShippingMethod($shippingMethod);
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
            ->andWhere(['handle' => $shippingMethodHandle])
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
            ->andWhere(['id' => $shippingMethodId])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeShippingMethod(new ShippingMethod($result));

        return $this->_shippingMethodsById[$shippingMethodId];
    }

    /**
     * Get all available shipping methods.
     *
     * @param Order $order
     * @return ShippingMethod[]
     */
    public function getAvailableShippingMethods(Order $order): array
    {
        $availableMethods = [];

        $methods = $this->getAllShippingMethods();

        $event = new RegisterAvailableShippingMethodsEvent([
            'shippingMethods' => $methods,
            'order' => $order
        ]);

        if ($this->hasEventHandlers(self::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS)) {
            $this->trigger(self::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, $event);
        }

        /** @var ShippingMethod $method */
        foreach ($event->shippingMethods as $method) {
            $totalPrice = $method->getPriceForOrder($order);

            if ($method->getIsEnabled() && $method->matchOrder($order)) {
                $availableMethods[$method->getHandle()] = [
                    'method' => $method,
                    'price' => $totalPrice, // Store the price so we can sort on it before returning
                ];
            }
        }

        // Sort by price. Using the cached price and don't call `$method->getPriceForOrder($order);` again.
        uasort($availableMethods, function($a, $b) {
            return $a['price'] - $b['price'];
        });

        $shippingMethods = [];
        foreach ($availableMethods as $shippingMethod) {
            $method = $shippingMethod['method'];
            $shippingMethods[$method->getHandle()] = $method; // Keep the key being the handle of the method for front-end use.
        }

        return $shippingMethods;
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
        return $method->getMatchingShippingRule($order);
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
                throw new Exception(Plugin::t( 'No shipping method exists with the ID “{id}”',
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
        $record->isLite = $model->isLite;

        $record->validate();
        $model->addErrors($record->getErrors());

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        return true;
    }

    /**
     * Save a lite shipping method.
     *
     * @param ShippingMethod $model
     * @param bool $runValidation should we validate this method before saving.
     * @return bool
     * @throws Exception
     */
    public function saveLiteShippingMethod(ShippingMethod $model, bool $runValidation = true): bool
    {
        $model->isLite = true;
        $model->id = null;

        // Delete the current lite shipping rules also first.
        Craft::$app->getDb()->createCommand()
            ->delete(ShippingRuleRecord::tableName(), ['isLite' => true])
            ->execute();

        // Delete the current lite shipping method.
        Craft::$app->getDb()->createCommand()
            ->delete(ShippingMethodRecord::tableName(), ['isLite' => true])
            ->execute();

        return $this->saveShippingMethod($model, $runValidation);
    }

    /**
     * Gets the the lite shipping method or returns a new one.
     *
     * @return ShippingMethod
     */
    public function getLiteShippingMethod(): ShippingMethod
    {
        $liteMethod = $this->_createShippingMethodQuery()->one();

        if ($liteMethod == null) {
            $liteMethod = new ShippingMethod();
            $liteMethod->isLite = true;
            $liteMethod->name = 'Shipping Cost';
            $liteMethod->handle = 'liteShipping';
            $liteMethod->enabled = true;
        } else {
            $liteMethod = new ShippingMethod($liteMethod);
        }

        return $liteMethod;
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
        $query = (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'enabled',
                'isLite'
            ])
            ->from([Table::SHIPPINGMETHODS]);

        if (Plugin::getInstance()->is(Plugin::EDITION_LITE)) {
            $query->andWhere('[[isLite]] = true');
        }

        return $query;
    }
}
