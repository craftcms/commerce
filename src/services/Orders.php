<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\db\Query;
use craft\elements\Address;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\errors\UnsupportedSiteException;
use craft\events\ConfigEvent;
use craft\events\ModelEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\models\FieldLayout;
use yii\base\Component;
use yii\base\Exception;
use yii\base\UserException;

/**
 * Orders service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Orders extends Component
{
    public const CONFIG_FIELDLAYOUT_KEY = 'commerce.orders.fieldLayouts';

    /**
     * Handle field layout change
     *
     * @throws Exception
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty(reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Order::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Order::class)->id;
        $layout->type = Order::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);
    }

    /**
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(Order::class);
    }

    /**
     * Get an order by its ID.
     *
     * @param int $id
     * @return ?Order
     */
    public function getOrderById(int $id): ?Order
    {
        if (!$id) {
            return null;
        }

        return Order::find()->id($id)->status(null)->one();
    }

    /**
     * Get an order by its number.
     */
    public function getOrderByNumber(string $number): ?Order
    {
        return Order::find()->number($number)->one();
    }

    /**
     * Get all orders by their customer.
     *
     * @param int|User $customer
     * @return Order[]|null
     */
    public function getOrdersByCustomer(User|int $customer): ?array
    {
        if (!$customer) {
            return null;
        }

        $query = Order::find();
        if ($customer instanceof User) {
            $query->customer($customer);
        } else {
            $query->customerId($customer);
        }
        $query->isCompleted();
        $query->limit(null);

        return $query->all();
    }

    /**
     * Get all orders by their email.
     *
     * @return Order[]|null
     */
    public function getOrdersByEmail(string $email): ?array
    {
        return Order::find()->email($email)->isCompleted()->limit(null)->all();
    }

    /**
     * @param array|Order[] $orders
     * @return Order[]
     * @since 4.0.0
     */
    public function eagerLoadAddressesForOrders(array $orders): array
    {
        $shippingAddressIds = array_filter(ArrayHelper::getColumn($orders, 'shippingAddressId'));
        $billingAddressIds = array_filter(ArrayHelper::getColumn($orders, 'billingAddressId'));
        $ids = array_unique(array_merge($shippingAddressIds, $billingAddressIds));

        // Query addresses as array to avoid instantiating elements immediately
        $query = Address::find()
            ->id($ids)
            ->indexBy('id')
            ->asArray();
        /** @var array $addresses */
        $addresses = $query->all();

        foreach ($orders as $key => $order) {
            if (isset($order['shippingAddressId'], $addresses[$order['shippingAddressId']])) {
                $data = $addresses[$order['shippingAddressId']];
                $data['owner'] = $order;
                /** @var Address $address */
                $address = $query->createElement($data);

                $order->setShippingAddress($address);
            }

            if (isset($order['billingAddressId'], $addresses[$order['billingAddressId']])) {
                $data = $addresses[$order['billingAddressId']];
                $data['owner'] = $order;

                /** @var Address $address */
                $address = $query->createElement($data);

                $order->setBillingAddress($address);
            }

            $orders[$key] = $order;
        }

        return $orders;
    }

    /**
     * Prevent deleting a user if they have any orders.
     *
     * @param ModelEvent $event the event.
     */
    public function beforeDeleteUserHandler(ModelEvent $event): void
    {
        /** @var User $user */
        $user = $event->sender;

        // If there are any orders, make sure that this is not allowed.
        if (Order::find()->customerId($user->id)->status(null)->exists()) {
            // TODO revise this stop-gap measure when Craft CMS gets a way to hook into the user delete process.
            throw new UserException(Craft::t('commerce', 'Unable to delete user {user}: the user has a Craft Commerce order.', [
                'user' => $user->id,
            ]));
        }
    }

    /**
     * @param ModelEvent $event
     * @return void
     * @throws Exception
     * @throws \Throwable
     * @throws ElementNotFoundException
     * @throws InvalidElementException
     * @throws UnsupportedSiteException
     * @since 4.2.11
     */
    public function afterSaveAddressHandler(ModelEvent $event): void
    {

        /** @var Address $address */
        $address = $event->sender;
        if ($address->getIsDraft()) {
            return;
        }

        // Find all orders using this address as a source
        $idQuery = (new Query())
            ->select(['id'])
            ->from(Table::ORDERS)
            ->where(['sourceBillingAddressId' => $address->id])
            ->orWhere(['sourceShippingAddressId' => $address->id]);

        /** @var Order[] $carts */
        $carts = Order::find()
            ->where(['commerce_orders.id' => $idQuery])
            ->isCompleted(false)
            ->all();

        if (empty($carts)) {
            return;
        }

        foreach ($carts as $cart) {
            // Update the billing address
            if ($cart->sourceBillingAddressId === $address->id) {
                $newBillingAddress = Craft::$app->getElements()->duplicateElement($address, ['primaryOwner' => $cart, 'title' => Craft::t('commerce', 'Billing Address')]);
                $cart->billingAddressId = $newBillingAddress->id;
            }

            // Update the shipping address
            if ($cart->sourceShippingAddressId === $address->id) {
                $newShippingAddress = Craft::$app->getElements()->duplicateElement($address, ['primaryOwner' => $cart, 'title' => Craft::t('commerce', 'Shipping Address')]);
                $cart->shippingAddressId = $newShippingAddress->id;
            }

            // Save the cart to trigger events and recalculations.
            Craft::$app->getElements()->saveElement($cart, false);
        }
    }
}
