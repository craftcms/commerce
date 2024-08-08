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
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Shipping method service.
 *
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
     * @var null|Collection<ShippingMethod>[]
     */
    private ?array $_allShippingMethods = null;

    /**
     * Returns the Commerce managed shipping methods stored in the database.
     *
     * @param int|null $storeId
     * @return Collection<ShippingMethod>
     * @throws InvalidConfigException
     */
    public function getAllShippingMethods(?int $storeId = null): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->_allShippingMethods === null || !isset($this->_allShippingMethods[$storeId])) {
            $results = $this->_createShippingMethodQuery()
                ->where(['storeId' => $storeId])
                ->all();

            if ($this->_allShippingMethods === null) {
                $this->_allShippingMethods = [];
            }

            foreach ($results as $result) {
                $shippingMethod = Craft::createObject([
                    'class' => ShippingMethod::class,
                    'attributes' => $result,
                ]);

                if (!isset($this->_allShippingMethods[$shippingMethod->storeId])) {
                    $this->_allShippingMethods[$shippingMethod->storeId] = collect();
                }

                $this->_allShippingMethods[$shippingMethod->storeId]->push($shippingMethod);
            }
        }

        return $this->_allShippingMethods[$storeId] ?? collect();
    }

    /**
     * Get a shipping method by its handle.
     */
    public function getShippingMethodByHandle(string $shippingMethodHandle, ?int $storeId = null): ?ShippingMethod
    {
        return $this->getAllShippingMethods($storeId)->firstWhere('handle', $shippingMethodHandle);
    }

    /**
     * Get a shipping method by its ID.
     */
    public function getShippingMethodById(int $shippingMethodId, ?int $storeId = null): ?ShippingMethod
    {
        return $this->getAllShippingMethods($storeId)->firstWhere('id', $shippingMethodId);
    }

    /**
     * Get all available shipping methods to the order.
     *
     * @return ShippingMethod[]
     */
    public function getMatchingShippingMethods(Order $order): array
    {
        $matchingMethods = [];

        $methods = $this->getAllShippingMethods($order->storeId);

        $event = new RegisterAvailableShippingMethodsEvent([
            'shippingMethods' => $methods,
            'order' => $order,
        ]);

        if ($this->hasEventHandlers(self::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS)) {
            $this->trigger(self::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, $event);
        }

        /** @var ShippingMethod $method */
        foreach ($event->getShippingMethods() as $method) {
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
            return ($a['price'] < $b['price']) ? -1 : 1;
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

        $record->storeId = $model->storeId;
        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->orderCondition = $model->getOrderCondition()->getConfig();
        $record->enabled = $model->enabled;

        $record->validate();
        $model->addErrors($record->getErrors());

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        $this->clearCache();

        return true;
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
            $this->clearCache();
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
        return (new Query())
            ->select([
                'dateCreated',
                'dateUpdated',
                'enabled',
                'handle',
                'id',
                'name',
                'orderCondition',
                'storeId',
            ])
            ->from([Table::SHIPPINGMETHODS]);
    }

    /**
     * @return void
     * @since 5.0.0
     */
    protected function clearCache(): void
    {
        $this->_allShippingMethods = null;
    }
}
