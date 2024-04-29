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
use craft\db\Query;
use craft\helpers\ArrayHelper;
use Throwable;
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
    public const EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS = 'registerAvailableShippingMethods';


    /**
     * @var null|ShippingMethod[]
     */
    private ?array $_allShippingMethods = null;

    /**
     * Returns the Commerce managed shipping methods stored in the database.
     *
     * @return ShippingMethod[]
     */
    public function getAllShippingMethods(): array
    {
        if ($this->_allShippingMethods !== null) {
            return $this->_allShippingMethods;
        }

        $results = $this->_createShippingMethodQuery()->all();
        $this->_allShippingMethods = [];

        foreach ($results as $result) {
            $shippingMethod = new ShippingMethod($result);

            $this->_allShippingMethods[] = $shippingMethod;
        }

        return $this->_allShippingMethods;
    }

    /**
     * Get a shipping method by its handle.
     */
    public function getShippingMethodByHandle(string $shippingMethodHandle): ?ShippingMethod
    {
        return ArrayHelper::firstWhere($this->getAllShippingMethods(), 'handle', $shippingMethodHandle);
    }

    /**
     * Get a shipping method by its ID.
     */
    public function getShippingMethodById(int $shippingMethodId): ?ShippingMethod
    {
        return ArrayHelper::firstWhere($this->getAllShippingMethods(), 'id', $shippingMethodId);
    }

    /**
     * Get all available shipping methods to the order.
     *
     * @return ShippingMethod[]
     */
    public function getMatchingShippingMethods(Order $order): array
    {
        $matchingMethods = [];

        $methods = $this->getAllShippingMethods();

        $event = new RegisterAvailableShippingMethodsEvent([
            'shippingMethods' => $methods,
            'order' => $order,
        ]);

        if ($this->hasEventHandlers(self::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS)) {
            $this->trigger(self::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, $event);
        }

        /** @var ShippingMethod $method */
        foreach ($event->shippingMethods as $method) {
            $totalPrice = $method->getPriceForOrder($order);

            if ($method->getIsEnabled() && $method->matchOrder($order)) {
                $matchingMethods[$method->getHandle()] = [
                    'method' => $method,
                    'price' => $totalPrice, // Store the price so we can sort on it before returning
                ];
            }
        }

        // Sort by price. Using the cached price and don't call `$method->getPriceForOrder($order);` again.
        uasort($matchingMethods, static function($a, $b) {
            return $a['price'] - $b['price'];
        });

        $shippingMethods = [];
        foreach ($matchingMethods as $shippingMethod) {
            $method = $shippingMethod['method'];
            $shippingMethods[$method->getHandle()] = $method; // Keep the key being the handle of the method for front-end use.
        }

        return $shippingMethods;
    }

    /**
     * Get a matching shipping rule for Order and shipping method.
     *
     * @noinspection PhpUnused
     */
    public function getMatchingShippingRule(Order $order, ShippingMethodInterface $method): ?ShippingRuleInterface
    {
        return $method->getMatchingShippingRule($order);
    }

    /**
     * Save a shipping method.
     *
     * @param bool $runValidation should we validate this method before saving.
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

        $this->_allShippingMethods = null; //clear the cache

        return true;
    }

    /**
     * Save a lite shipping method.
     *
     * @param bool $runValidation should we validate this method before saving.
     * @throws Exception
     * @deprecated in 4.5.0. Use [[saveShippingMethod()]] instead.
     */
    public function saveLiteShippingMethod(ShippingMethod $model, bool $runValidation = true): bool
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'ShippingMethods::saveLiteShippingMethods() is deprecated. Use ShippingMethods::saveShippingMethod() instead.');
        $this->_allShippingMethods = null; //clear the cache
        return $this->saveShippingMethod($model, $runValidation);
    }

    /**
     * Gets the lite shipping method or returns a new one.
     * @return ShippingMethod
     * @deprecated in 4.5.0. Use [[getAllShippingMethods()]] instead.
     */
    public function getLiteShippingMethod(): ShippingMethod
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'ShippingMethods::getLiteShippingMethod() is deprecated. Use ShippingMethods::getAllShippingMethods() instead.');
        $liteMethod = $this->_createShippingMethodQuery()->one();

        if ($liteMethod == null) {
            $liteMethod = new ShippingMethod();
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
     * @param int $shippingMethodId
     * @return bool
     * @throws Throwable
     */
    public function deleteShippingMethodById(int $shippingMethodId): bool
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
            $this->_allShippingMethods = null; //clear the cache
            return true;
        } catch (\Exception) {
            $transaction->rollBack();

            return false;
        }
    }

    /**
     * Returns a Query object prepped for retrieving shipping methods.
     */
    private function _createShippingMethodQuery(): Query
    {
        $query = (new Query())
            ->select([
                'dateCreated',
                'dateUpdated',
                'enabled',
                'handle',
                'id',
                'name',
            ])
            ->from([Table::SHIPPINGMETHODS]);

        return $query;
    }
}
