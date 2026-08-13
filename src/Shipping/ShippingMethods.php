<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping;

use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use CraftCms\Commerce\Shipping\Contracts\ShippingRuleInterface;
use CraftCms\Commerce\Shipping\Events\RegisterAvailableShippingMethodsEvent;
use CraftCms\Commerce\Shipping\Models\BaseShippingMethod;
use CraftCms\Commerce\Shipping\Models\ShippingMethod;
use CraftCms\Commerce\Shipping\Records\ShippingMethod as ShippingMethodRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use function CraftCms\Cms\t;

#[Singleton]
class ShippingMethods
{
    public const EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS = 'registerAvailableShippingMethods';

    /** @var array<int, Collection<int, ShippingMethod>>|null */
    private ?array $allShippingMethods = null;

    /** @var array<string, array> */
    private array $serializedOrdersByNumber = [];

    /**
     * @return Collection<int, ShippingMethod>
     */
    public function getAllShippingMethods(?int $storeId = null): Collection
    {
        $storeId ??= $this->currentStoreId();

        if ($this->allShippingMethods === null || !isset($this->allShippingMethods[$storeId])) {
            $rows = $this->query()->where('storeId', $storeId)->get()->all();

            $this->allShippingMethods ??= [];

            foreach ($rows as $row) {
                $method = new ShippingMethod((array) $row);
                $this->allShippingMethods[$method->storeId] ??= collect();
                $this->allShippingMethods[$method->storeId]->push($method);
            }
        }

        return $this->allShippingMethods[$storeId] ?? collect();
    }

    public function getShippingMethodByHandle(string $handle, ?int $storeId = null): ?ShippingMethod
    {
        return $this->getAllShippingMethods($storeId)->firstWhere('handle', $handle);
    }

    public function getShippingMethodById(int $id, ?int $storeId = null): ?ShippingMethod
    {
        return $this->getAllShippingMethods($storeId)->firstWhere('id', $id);
    }

    /**
     * Returns all shipping methods that match the given order, sorted by price.
     *
     * @return array<string, ShippingMethod>
     */
    public function getMatchingShippingMethods(Order $order): array
    {
        $methods = $this->getAllShippingMethods($order->storeId);

        $event = new RegisterAvailableShippingMethodsEvent(
            order: $order,
        );
        $event->setShippingMethods($methods);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getShippingMethods()->hasEventHandlers(self::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getShippingMethods()->trigger(self::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, $event);
        }

        $matchingMethods = [];
        foreach ($event->getShippingMethods() as $method) {
            if ($method->getIsEnabled() && $method->matchOrder($order)) {
                // Now we know the method matches, let's get the price
                $totalPrice = $method->getPriceForOrder($order);

                $matchingMethods[$method->getHandle()] = [
                    'method' => $method,
                    'price' => $totalPrice,
                ];
            }
        }

        uasort($matchingMethods, static fn($a, $b) => $a['price'] <=> $b['price']);

        $shippingMethods = [];
        foreach ($matchingMethods as $item) {
            $method = $item['method'];
            $shippingMethods[$method->getHandle()] = $method;

            // Clear the matching cache in case things change in the future
            if ($method instanceof BaseShippingMethod) {
                $method->clearMatchingShippingRuleCache();
            }
        }

        $this->serializedOrdersByNumber = [];

        return $shippingMethods;
    }

    public function getSerializedOrderForMatchingRules(Order $order): array
    {
        if (isset($this->serializedOrdersByNumber[$order->number])) {
            return $this->serializedOrdersByNumber[$order->number];
        }

        /** @phpstan-ignore-next-line */
        $fieldsAsArray = $order->getSerializedFieldValues();
        /** @phpstan-ignore-next-line */
        $orderAsArray = $order->toArray([], ['lineItems.snapshot', 'shippingAddress', 'billingAddress']);
        $this->serializedOrdersByNumber[$order->number] = array_merge($orderAsArray, $fieldsAsArray);

        return $this->serializedOrdersByNumber[$order->number];
    }

    public function getMatchingShippingRule(Order $order, ShippingMethodInterface $method): ?ShippingRuleInterface
    {
        return $method->getMatchingShippingRule($order);
    }

    public function saveShippingMethod(ShippingMethod $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = ShippingMethodRecord::find($model->id);
            if (!$record) {
                throw new \RuntimeException(t('No shipping method exists with the ID "{id}"', ['id' => $model->id], category: 'commerce'));
            }
        } else {
            $record = new ShippingMethodRecord();
        }

        if ($runValidation && !$model->validate()) {
            return false;
        }

        $record->storeId = $model->storeId;
        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->icon = $model->icon;
        $record->color = $model->color;
        /** @phpstan-ignore-next-line */
        $record->orderCondition = $model->getOrderCondition()->getConfig();
        /** @phpstan-ignore-next-line */
        $record->customerCondition = $model->getCustomerCondition()->getConfig();
        $record->enabled = $model->enabled;

        $record->save();
        $model->id = $record->id;

        $this->clearCache();

        return true;
    }

    public function deleteShippingMethodById(int $id): bool
    {
        DB::beginTransaction();

        try {
            $rules = app(ShippingRules::class)->getAllShippingRulesByShippingMethodId($id);

            foreach ($rules as $rule) {
                app(ShippingRules::class)->deleteShippingRuleById($rule->id);
            }

            $record = ShippingMethodRecord::find($id);
            $record->delete();

            DB::commit();
            $this->clearCache();

            return true;
        } catch (\Exception) {
            DB::rollBack();

            return false;
        }
    }

    public function clearCache(): void
    {
        $this->allShippingMethods = null;
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        $query = DB::table(Table::SHIPPINGMETHODS)
            ->select([
                'storeId',
                'id',
                'name',
                'handle',
                'enabled',
                'orderCondition',
                'customerCondition',
                'dateCreated',
                'dateUpdated',
            ]);

        if (Schema::hasColumn(Table::SHIPPINGMETHODS, 'icon')) {
            $query->addSelect(['icon', 'color']);
        }

        return $query;
    }

    private function currentStoreId(): int
    {
        // TODO: migrate to app(Stores::class)->getCurrentStore()->id once Stores service migrated
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getStores()->getCurrentStore()->id;
    }
}
